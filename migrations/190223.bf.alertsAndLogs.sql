CREATE DATABASE IF NOT EXISTS alptech;
use alptech;

CREATE TABLE IF NOT EXISTS migrations (id int(20) NOT NULL,k varchar(255) DEFAULT NULL,date datetime DEFAULT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS alerts (id int(20) NOT NULL,k varchar(255) DEFAULT NULL,v varchar(255) DEFAULT NULL,date datetime DEFAULT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS logs (id int(20) NOT NULL,host varchar(40) DEFAULT NULL,ip varchar(40) DEFAULT NULL,type varchar(30) DEFAULT NULL,k varchar(255) DEFAULT NULL,k2
varchar(255) DEFAULT NULL,k3 varchar(255) DEFAULT NULL,k4 varchar(255)  DEFAULT NULL,k5 varchar(255) DEFAULT NULL,k6 varchar(255) DEFAULT NULL,k7 varchar(255) DEFAULT NULL,v longtext,json json DEFAULT NULL,date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,severity int(20) DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='logsCollection' ROW_FORMAT=DYNAMIC;

ALTER TABLE alerts ADD PRIMARY KEY (id),ADD KEY k (k),ADD KEY date (date);
ALTER TABLE logs ADD PRIMARY KEY (id),ADD KEY host (host),  ADD KEY type (type),  ADD KEY k (k),  ADD KEY date (date),  ADD KEY severity (severity),  ADD KEY ip (ip),  ADD KEY k2 (k2),  ADD KEY k3 (k3),  ADD KEY k4 (k4),  ADD KEY k5 (k5),  ADD KEY k6 (k6),ADD KEY k7 (k7);

ALTER TABLE migrations ADD PRIMARY KEY (id);
ALTER TABLE migrations MODIFY id int(20) NOT NULL AUTO_INCREMENT;
ALTER TABLE alerts MODIFY id int(20) NOT NULL AUTO_INCREMENT;
ALTER TABLE logs MODIFY id int(20) NOT NULL AUTO_INCREMENT;
