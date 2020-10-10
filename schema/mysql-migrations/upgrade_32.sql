DROP TABLE vspheredb_daemonlog;

CREATE TABLE vspheredb_daemonlog (
  ts_create BIGINT(20) UNSIGNED NOT NULL,
  instance_uuid VARBINARY(16) NOT NULL,
  pid INT UNSIGNED NOT NULL,
  fqdn VARCHAR(255) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  vcenter_uuid VARBINARY(16) DEFAULT NULL,
  level ENUM(
    'debug',
    'info',
    'notice',
    'warning',
    'error',
    'critical',
    'emergency'
  ) NOT NULL,
  message MEDIUMTEXT NOT NULL,
  INDEX idx_time (ts_create)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO vspheredb_schema_migration
  (schema_version, migration_time)
  VALUES (32, NOW());
