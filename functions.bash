#
#. functions.bash
function get() {
    x=`phpx cli.php get $@;`;read "$1" <<< $x;echo "get $1=$x"; };

function set() {
    IFS='¤¤¤';read "$1" <<< $2;#sets global for bash system
    phpx cli.php set $@;# | tee ~/bash.results;
    #res=`$c`;ec "$c ... $res";#not havin the right path ?
    nl;return;
};

function nl() { IFS=$'\n'; };#IFS STANDARD
function reloadVars() {
#bash parse JSON to Associative array
#https://stackoverflow.com/questions/28179409/bash-array-assignment-fails-if-you-declare-the-array-in-advance
    IFS='§';declare -A ar;get all;eval ar=$all;#echo $ar;#triggers error with parenthesis
    for k in "${!ar[@]}";do v=$(echo ${ar[$k]} | sed -e "s/(/\\\(/g" | sed -e "s/)/\\\)/g");echo "$k:$v";eval $k=$v;done;#foreach eval to root#get a from JSON as an array key
    nl;
}
