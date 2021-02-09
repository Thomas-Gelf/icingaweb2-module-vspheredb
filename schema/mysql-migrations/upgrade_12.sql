DROP TABLE performance_counter;
DROP TABLE performance_collection_interval;
DROP TABLE performance_group;
DROP TABLE performance_unit;

CREATE TABLE performance_unit (
  vcenter_uuid VARBINARY(16) NOT NULL,
  name VARCHAR(32) NOT NULL,
  label VARCHAR(16) NOT NULL,
  summary VARCHAR(64) NOT NULL,
  PRIMARY KEY (vcenter_uuid, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE performance_group (
  vcenter_uuid VARBINARY(16) NOT NULL,
  name VARCHAR(32) NOT NULL,
  label VARCHAR(48) NOT NULL,
  summary VARCHAR(64) NOT NULL,
  PRIMARY KEY (vcenter_uuid, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE performance_collection_interval (
  vcenter_uuid VARBINARY(16) NOT NULL,
  name VARCHAR(32) NOT NULL,
  label VARCHAR(48) NOT NULL,
  summary VARCHAR(64) NOT NULL,
  PRIMARY KEY (vcenter_uuid, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE performance_counter (
  vcenter_uuid VARBINARY(16) NOT NULL,
  counter_key INT UNSIGNED NOT NULL,
  name VARCHAR(32) NOT NULL COLLATE utf8_bin,
  label VARCHAR(255) NOT NULL,
  group_name VARCHAR(32) NOT NULL,
  unit_name VARCHAR(32) NOT NULL,
  summary text NOT NULL,
  stats_type ENUM( -- statsType
    'absolute',
    'delta',
    'rate'
  ) NOT NULL,
  rollup_type ENUM(  -- rollupType
    'average',
    'maximum',
    'minimum',
    'latest',
    'summation',
    'none'
  ) NOT NULL,
  level TINYINT UNSIGNED NOT NULL, -- level 1-4
  per_device_level TINYINT UNSIGNED NOT NULL, -- perDeviceLevel 1-4
  -- collection_interval INT UNSIGNED NOT NULL, -- 300, 86400... -> nur pro el?
  PRIMARY KEY (vcenter_uuid, counter_key),
  -- UNIQUE INDEX combined (vcenter_uuid, group_name, name, unit_name),
  CONSTRAINT performance_counter_vcenter
    FOREIGN KEY vcenter (vcenter_uuid)
    REFERENCES vcenter (instance_uuid)
      ON DELETE RESTRICT
      ON UPDATE RESTRICT,
  CONSTRAINT performance_counter_group
    FOREIGN KEY performance_group (vcenter_uuid, group_name)
    REFERENCES performance_group (vcenter_uuid, name)
      ON DELETE RESTRICT
      ON UPDATE RESTRICT,
  CONSTRAINT performance_counter_unit
    FOREIGN KEY performance_unit (vcenter_uuid, unit_name)
    REFERENCES performance_unit (vcenter_uuid, name)
      ON DELETE RESTRICT
      ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO vspheredb_schema_migration
  (schema_version, migration_time)
VALUES (12, NOW());
