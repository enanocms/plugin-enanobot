CREATE TABLE snippets(
  snippet_id int(12) NOT NULL auto_increment,
  snippet_code varchar(32) NOT NULL DEFAULT '',
  snippet_text text,
  snippet_channels text,
  PRIMARY KEY ( snippet_id )
);

CREATE TABLE irclog (
  id int(11) NOT NULL auto_increment,
  channel varchar(30) default NULL,
  day char(10) default NULL,
  nick varchar(40) default NULL,
  timestamp int(11) default NULL,
  line text,
  spam tinyint(1) default '0',
  PRIMARY KEY  (id)
);

--
-- NEW - Late October '08 modifications
--

CREATE TABLE stats_messages (
  message_id int(21) NOT NULL auto_increment,
  channel varchar(30) NOT NULL DEFAULT '',
  nick varchar(40) NOT NULL DEFAULT '',
  time int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY ( message_id )
);

CREATE TABLE stats_anon (
  nick varchar(40) NOT NULL DEFAULT '',
  PRIMARY KEY ( nick )
);

CREATE TABLE stats_count_cache (
  cache_id int(21) NOT NULL auto_increment,
  channel varchar(30) NOT NULL DEFAULT '',
  time_min int(11) NOT NULL DEFAULT 0,
  time_max int(11) NOT NULL DEFAULT 0,
  message_count int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY ( cache_id )
);

--
-- ADDED November 15 2008
--

CREATE TABLE ip_log (
  entry_id int(21) NOT NULL auto_increment,
  nick varchar(40) NOT NULL,
  basenick varchar(40) NOT NULL,
  ip varchar(39) NOT NULL,
  hostname varchar(80) NOT NULL,
  channel varchar(20) NOT NULL,
  time int(12) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY ( entry_id )
);

-- Also added Nov. 15 (this DRAMATICALLY speeds things up)
CREATE INDEX stats_time_idx USING BTREE ON stats_messages (time);

