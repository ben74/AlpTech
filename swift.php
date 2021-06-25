<?php
//use Alptech\wip\swift
/*
swift::$redis = redisConnection()
swift::$user = 'xx';
swift::$password = 'pp';
swift::$authUrl = 'https://swift.com/identity/v3';
swift::putFile('yo');
 */

namespace Alptech\Wip;

class swift
{
    static $swiftSegmentSize = 314572800, $redisAuthKeyExpirationTreshold = 100, $monologPath = '/monolog.log', $redis = null, $authUrl = '', $user = '', $password = '', $domain = 'default', $rk1 = 'swift::token::', $rk2 = 'swift::endpoint::';

    /**
     * @param $x
     * @return bool
     */
    static function unlink($x)
    {
        $a = 1;
        return @unlink($x);
    }

    /**
     * @param $x
     * @return false|int
     */
    static function log($x)
    {
        if (!isset($_ENV['monolog'])) {
            $x = explode('/', $GLOBALS['argv'][0]);
            $_ENV['monolog'] = $_ENV['node'] . ':' . str_replace('.php', '', end($x)) . ':' . getmypid();
        }
        echo "\n" . $x;
        return file_put_contents(static::$monologPath, "\n" . $_ENV['monolog'] . ':' . $x, 8);
    }

    /**
     * @param $file
     * @param $channel
     * @param $objectId
     * @param int $unlink
     * @param int $segSize
     * @param int $timeout
     * @param int $maxTries
     * @param null $md5
     * @param int $maxFragmentedTries
     * @param string $dir
     * @return array
     * @throws \Exception
     */
    static function putFile($file, $channel, $objectId, $unlink = 0, $segSize = 314572800, $timeout = 99999, $maxTries = 5, $md5 = null, $maxFragmentedTries = 5, $dir = '/tmp/splits/')
    {
        [$token, $endpoint, $expires] = static::swiftAutoToken();
        //https://swift02-prx.cloud.infomaniak.ch/object/v1/AUTH_63c53f4756e3482994a2b7bcca1a16a7
        return static::filePut($endpoint, $token, $file, $channel, $objectId, $unlink, $segSize, $timeout, $maxTries, $md5, $maxFragmentedTries, $dir);
    }

    /**
     * Returns a token + endpoint url is necessary
     * @return array
     */
    static function swiftAutoToken()
    {
        $token = null;
        //static::$redis->del(static::$rk1);
        if (static::$redis) {
            $token = static::$redis->get(static::$rk1);
            $expiresIn = static::$redis->ttl(static::$rk1);
            $endPointUrl = static::$redis->get(static::$rk2);
        }

        if (!$token) {
            $post = ['auth' => ['identity' => ['password' => ['user' => ['name' => static::$user, 'password' => static::$password, 'domain' => ['name' => static::$domain]]], 'methods' => [0 => 'password']]]];
            [$token, $endPointUrl, $expiresIn] = static::getToken(static::$authUrl, $post);
            if (static::$redis) {
                static::$redis->set(static::$rk1, $token, $expiresIn - static::$redisAuthKeyExpirationTreshold);
                static::$redis->set(static::$rk2, $endPointUrl, $expiresIn - static::$redisAuthKeyExpirationTreshold);
            }
        }
        return [$token, $endPointUrl, $expiresIn];
    }


    /**
     * @param $file
     * @param int $bufferSize
     * @param string $dir
     * @param string $prefix
     * @return array
     * Split keeping memory usage at low profile
     */
    static function fsplit($file, $bufferSize = 104857600, $dir = '/tmp/splits/', $yield = 0)
    {
        if (!$bufferSize) {
            $bufferSize = statiic::$swiftSegmentSize;
        }

// Préserver l'intégrité de plusieurs splits concourrents sur le même fichier de base
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $total = $i = 0;
        $file_parts = [];
        $file_size = filesize($file);
        static $file_handle = null;
        if (!$file_handle) {
            $file_handle = fopen($file, 'r');
        }
        $file_name = basename($file);
        while ($total < $file_size) {
            $i++;
//important pour l'ordre au sein d'un folder
            $file_parts[] = $file_part_path = $dir . $file_name . '.' . str_pad((string)$i, 5, '0', STR_PAD_LEFT);
            $file_new = fopen($file_part_path, 'w+');
            $read = 0;
            while ($read < $bufferSize and $total < $file_size) {
                $unit = 4096;
                $remaining = $bufferSize - $read;
                if ($remaining < $unit) {
                    $unit = $remaining;
                }//Dernière écriture
                $file_part = fread($file_handle, $unit);
                $read += strlen($file_part);
                $total += strlen($file_part);
                fwrite($file_new, $file_part);
            }
            fclose($file_new);
            if ($yield) {
                yield ($file_part_path);
            }
        }
        fclose($file_handle);
        $file_handle = null;
        return $file_parts;
    }

    /**
     * @param $response
     * @return array|false
     */
    static function getHeaders($response)
    {
        if (!preg_match_all('/([A-Za-z\-]{1,})\:(.*)\\r/', $response, $matches)
            || !isset($matches[1], $matches[2])) {
            return false;
        }
        $headers = [];
        foreach ($matches[1] as $index => $key) {
            $headers[$key] = trim($matches[2][$index], ' "');
        }
        return $headers;
    }

    /**
     * @param $endpoint
     * @param $token
     * @param $file
     * @param $channel
     * @param $objectId
     * @param int $unlink
     * @param int $segSize
     * @param int $timeout
     * @param int $maxTries
     * @param null $md5
     * @param int $maxFragmentedTries
     * @return array
     * @throws \Exception
     *
     * Envoi de fichier simple si < $segSize, sinon segmentation et passage de chaque dans filePutUnique ( final )
     */
    static function filePut($endpoint, $token, $file, $channel, $objectId, $unlink = 0, $segSize = 314572800, $timeout = 99999, $maxTries = 5, $md5 = null, $maxFragmentedTries = 5, $dir = '/tmp/splits/')
    {
        if (!is_file($file)) {
            static::log('File not found on disk : ' . $file);
            throw new \Exception('File not found on disk : ' . $file);
        }

        $fs = filesize($file);
        $unlinks = $fragments = [];
        $totRetries = $manifestOk = $nbFragments = 0;

        if ($fs > $segSize) {
            $etag = $retries = $toTries = 0;
            $dir .= uniqid() . '/';
            while (!$manifestOk and $toTries < $maxFragmentedTries) {
                try {
                    $toTries++;//Retries
                    $md5s = $manifestItems = [];
                    foreach (static::fsplit($file, $segSize, $dir, 1) as $idChunk => $fragment) {
                        $fragments[] = $fragment;
                        if (!is_file($fragment)) {
                            static::log('>Fragment : ' . $file . '#' . $fragment . ' not found on disk');
                            throw new \Exception('Fragment : ' . $fragment . ' not found on disk');
                        }
                        $ok = 0;
                        $name = $objectId . '/' . str_pad((string)$idChunk, 5, '0', STR_PAD_LEFT);
                        $md5s[] = $md5 = md5_file($fragment);
                        $manifestItems[] = ['path' => '/' . $channel . '/' . $name, 'etag' => $md5, 'size_bytes' => filesize($fragment)];
                        try {
                            [, $ok, $retries, $etag,] = static::filePutUnique($endpoint, $token, $fragment, $channel, $name, 0, $segSize, $timeout, $maxTries);
                        } catch (\Throwable $e) {
// Retries within filePutUnique
                        }
                        if (!$ok) {
                            static::log(">Upload failed with " . $retries . ' tries at chunk#' . $idChunk . ' with ' . $etag . ' instead of ' . $md5);
                            throw new \Exception("\nUpload failed with " . $retries . ' tries at chunk#' . $idChunk . ' with ' . $etag . ' instead of ' . $md5);
                        } else {
                            $unlinks[] = $fragment;
                            static::unlink($fragment);//non requis, et si oubli unlink source ..
                        }
                    }
                    $nbFragments = count($fragments);

                    if ($unlink) {
                        $unlinks[] = $file;
                        static::unlink($file);//source
                    }

                    $manifest = tempnam('/tmp', 'manifest');
                    file_put_contents($manifest, json_encode($manifestItems));
                    $md5 = md5(implode('', $md5s));
                    [$responseCode, $manifestOk, $essaisParFragments, $etag] = static::filePutUnique($endpoint, $token, $manifest, $channel, $objectId . '?multipart-manifest=put', $unlink, $segSize, $timeout, $maxTries, $md5);

                    if ($manifestOk) {
                        $unlinks[] = $dir;
                        rmdir($dir);
                        static::log('>Manifest ok');
                        return [$responseCode, $manifestOk, $totRetries, $etag, $nbFragments];
                    } else {
                        static::log("#err#Manifest ko#" . $retries . ":" . $objectId);
                    }
                    $totRetries += $essaisParFragments;
                } catch (\Throwable $e) {
// Retries
                }
            }
// Si c'est un échec alors on efface tous les fragments générés
            foreach ($fragments as $idChunk => $fragment) {
                static::unlink($fragment);
            }
            rmdir($dir);
            return [0, 0, $totRetries, 0, $nbFragments];
        }
//simple upload then
        return static::filePutUnique($endpoint, $token, $file, $channel, $objectId, $unlink, $segSize, $timeout, $maxTries, $md5);
    }

    /**
     * @param $endpoint
     * @param $token
     * @param $file
     * @param $channel
     * @param $objectId
     * @param int $unlink
     * @param int $segSize
     * @param int $timeout
     * @param int $maxTries
     * @param null $md5
     * @return array
     * @throws \Exception
     */
    static function filePutUnique($endpoint, $token, $file, $channel, $objectId, $unlink = 0, $segSize = 314572800, $timeout = 99999, $maxTries = 5, $md5 = null)
    {
        if (!is_file($file)) {
            static::log('>File not found on disk : ' . $file);
            throw new \Exception('File not found on disk : ' . $file);
        }

        $nbFragments = 1;
        $stream = $responseCode = $ok = $etag = $retries = $isManifest = 0;
        $headers = ['Etag' => 0];
        if ($md5) {
            $isManifest = 1;
        } else {
            $md5 = md5_file($file);
        }
        $url = $channel . '/' . $objectId;
        $o = [
            CURLOPT_PUT => 1,
            CURLOPT_URL => $endpoint . '/' . $url,
            CURLOPT_INFILESIZE => filesize($file),
            CURLOPT_USERAGENT => 'ben',
            CURLOPT_HEADER => 1,
            CURLOPT_HTTPHEADER => ["Etag: " . $md5, "X-Auth-Token: " . $token, "Content-Length: " . filesize($file), "Accept:", "Expect:", "Accept-Encoding:", "User-Agent: ben", "Content-Type: video/mp4",]
            ,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout
            ,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_PROTOCOLS => 3,
            CURLOPT_HTTP_VERSION => 2,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true
        ];
        $retries = -1;
        $ok = 0;

        while (!$ok && $retries < $maxTries) {
            $retries++;
            $c = \curl_init();
            $stream = fopen($file, 'rb');
            $o[CURLOPT_INFILE] = $stream;
            \curl_setopt_array($c, $o);
            $response = \curl_exec($c);
            $e = \curl_error($c);
            $__i = curl_getinfo($c);
            if ($e) {
                static::log('#err#swiftError#' . $retries . ' : ' . $e . ' : ' . $file . ' > ' . $url);
                continue;
                throw new \Exception($e);
            }
            $responseCode = $__i['http_code'];
            if ($responseCode == 408) {// il faut ré-essayer
                static::log('#err#swift:408#' . $retries . ' : ' . $file . ' > ' . $url);
                continue;
                throw new \Exception(__LINE__ . '#response code:' . $responseCode);
            }
            if ($responseCode != 201) {//401,411, 422 => why ??
                static::log('#err#swift:' . $responseCode . '#' . $retries . ' : ' . $file . ' > ' . $url);
                continue;
                throw new \Exception(__LINE__ . '#response code:' . $responseCode);
            }

            $header_size = $__i['header_size'];
            $headers = static::getHeaders(substr($response, 0, $header_size));
            $md5result = trim($headers['Etag'], ' "');
            if ($md5 == $md5result) {
                $ok = 1;
            } else {// Réessaye l'upload
                static::log('#erre1swift:badmd5#' . $retries . ' : ' . $file . ' > ' . $url . ':' . $md5 . '<>' . $md5result . '#' . $retries);
            }
        }
        fclose($stream);
        if ($unlink) {
            static::unlink($file);
        }
        if ($ok) static::log('>swift:ok : ' . $file . ' > ' . $url);
        else {
            static::log('>swift:ko : ' . $file . ' > ' . $url);
        }
        return [$responseCode, $ok, $retries, $headers['Etag'], $nbFragments];//201 created
    }

    static function getToken($baseURI, $post, $timeout = 90)
    {//CURLOPT_SSL_VERIFYPEER => 0, CURLOPT_SSL_VERIFYHOST => 0,
        $o = [CURLOPT_USERAGENT => 'ben', CURLOPT_FOLLOWLOCATION => 1, CURLOPT_HEADER => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => $timeout, CURLOPT_POST => 1, CURLOPT_URL => $baseURI . "/auth/tokens", CURLOPT_POSTFIELDS => json_encode($post), CURLOPT_HTTPHEADER => ["Expect:", "Accept-Encoding:", "User-Agent: ben", "Content-Type: application/json", "Accept:"]];
        $c = \curl_init();
        \curl_setopt_array($c, $o);
        $response = \curl_exec($c);
        $__i = \curl_getinfo($c);
        if ($__i["http_code"] != 201) {
            static::log('#err#auth:' . $__i["http_code"]);
            throw new \Exception($__i["http_code"]);
        }
        $e = \curl_error($c);
        if ($e) {
            throw new \Exception($e);
        }
        try {
            $endPointUrl = '';
            $header_size = $__i['header_size'];
            $headers = static::getHeaders(substr($response, 0, $header_size));
            $body = json_decode(substr($response, $header_size), true);
            //$projectId = "AUTH_" . $__body["token"]["project"]["id"];
            $catalogs = $body["token"]["catalog"];
            foreach ($catalogs as $catalog) {
                if ($catalog['type'] == 'object-store') {
                    $endPointUrl = $catalog['endpoints'][0]["url"];
                    break;
                }
            }
            $expiresIn = strtotime($body["token"]["expires_at"]) - time();
            $token = $headers["X-Subject-Token"];
        } catch (\throwable $e) {
            static::log('#err#auth:' . $e->getMessage());
            throw new \Exception($e);
        }
        return [$token, $endPointUrl, $expiresIn];
    }
}
