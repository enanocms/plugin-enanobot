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

