SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION,PIPES_AS_CONCAT,ANSI_QUOTES,ERROR_FOR_DIVISION_BY_ZERO';

-- CREATE TABLE trust_store (
--   id INT UNSIGNED NOT NULL AUTO_INCREMENT,
--   certificate
-- );

-- CREATE TABLE trust_store_ca (
--   trust_store_id
-- );

CREATE TABLE vspheredb_schema_migration (
  schema_version SMALLINT UNSIGNED NOT NULL,
  migration_time DATETIME NOT NULL,
  PRIMARY KEY(schema_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vcenter (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  instance_uuid VARBINARY(16) NOT NULL,
  trust_store_id INT UNSIGNED DEFAULT NULL, -- TODO: not null?
  name VARCHAR(64) NOT NULL,    -- configured name
  version VARCHAR(10) NOT NULL, -- version	"6.0.0"
  os_type VARCHAR(32) NOT NULL, -- osType	"linux-x64"
  api_name VARCHAR(64) NOT NULL, -- name	"VMware vCenter Server"
  api_type VARCHAR(64) NOT NULL, -- apiType	"VirtualCenter"
  api_version VARCHAR(10) NOT NULL, -- apiVersion	"6.0"
  build VARCHAR(32) DEFAULT NULL, -- build "5318203"
  -- fullName -> "<api_type> <version> build-<build>"
  vendor VARCHAR(64) NOT NULL, -- vendor	"VMware, Inc."
  product_line VARCHAR(32) DEFAULT NULL, -- productLineId	string	"vpx"
  license_product_name VARCHAR(64) DEFAULT NULL, -- licenseProductName	"VMware VirtualCenter Server"
  license_product_version VARCHAR(10) DEFAULT NULL, -- licenseProductVersion"6.0"
  locale_build VARCHAR(32) DEFAULT NULL, -- localeBuild	"000"
  locale_version VARCHAR(10) DEFAULT NULL, -- localeVersion	"INTL"
  PRIMARY KEY (`id`),
  UNIQUE KEY `instance_uuid` (`instance_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

-- currently: id!
CREATE TABLE vcenter_server (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  vcenter_id INT(10) UNSIGNED DEFAULT NULL,
  -- vcenter_uuid VARBINARY(16) NOT NULL,
  -- server_uuid VARBINARY(16) NOT NULL, -- md5(vcenter_uuid:scheme://host
  host VARCHAR(255) NOT NULL,
  scheme ENUM ('http', 'https') NOT NULL,
  username VARCHAR(64) NOT NULL,
  password VARCHAR(64) NOT NULL,
  proxy_type ENUM('HTTP', 'SOCKS5') DEFAULT NULL,
  proxy_address VARCHAR(255) DEFAULT NULL,
  proxy_user VARCHAR(64) DEFAULT NULL,
  proxy_pass VARCHAR(64) DEFAULT NULL,
  ssl_verify_peer ENUM ('y', 'n') NOT NULL,
  ssl_verify_host ENUM ('y', 'n') NOT NULL,
  enabled ENUM ('y', 'n') NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT server_vcenter
    FOREIGN KEY server_vcenter_uuid (vcenter_id)
    REFERENCES vcenter (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;


CREATE TABLE vspheredb_daemon (
  instance_uuid VARBINARY(16) NOT NULL, -- random by daemon
  fqdn VARCHAR(255) NOT NULL,
  username VARCHAR(64) NOT NULL,
  pid INT UNSIGNED NOT NULL,
  php_version VARCHAR(64) NOT NULL,
  ts_last_refresh BIGINT(20) UNSIGNED NOT NULL,
  process_info MEDIUMTEXT NOT NULL,
  PRIMARY KEY (instance_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;


CREATE TABLE vspheredb_daemonlog (
  vcenter_uuid VARBINARY(16) NOT NULL,
  instance_uuid VARBINARY(16) NOT NULL,
  ts_create BIGINT(20) UNSIGNED NOT NULL,
  level ENUM(
    'debug',
    'info',
    'warning',
    'error'
  ) NOT NULL,
  message MEDIUMTEXT NOT NULL,
  INDEX (vcenter_uuid, ts_create)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;


CREATE TABLE vcenter_sync (
  vcenter_uuid VARBINARY(16) NOT NULL,
  fqdn VARCHAR(255) NOT NULL,
  username VARCHAR(64) NOT NULL,
  pid INT UNSIGNED NOT NULL,
  php_version VARCHAR(64) NOT NULL,
  ts_last_refresh BIGINT(20) NOT NULL,
  PRIMARY KEY (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vcenter_session (
  session_id VARBINARY(20) NOT NULL, -- hex2bin(sid)
  vcenter_server_id INT(10) UNSIGNED NOT NULL,
  session_cookie_string VARCHAR(255) NOT NULL,
  session_cookie_name VARCHAR(64) NOT NULL,
  scope VARCHAR(32) NOT NULL,
  -- vmware_soap_session="72bb45defccdf97b1945e686228279af0c3746a9"; Path=/; HttpOnly; Secure;
  ts_created BIGINT(20) NOT NULL,
  ts_last_check BIGINT(20) NOT NULL,
  PRIMARY KEY (session_id),
  CONSTRAINT session_vcenter_server
    FOREIGN KEY session_vcenter_server_id (vcenter_server_id)
    REFERENCES vcenter_server (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vcenter_event_history_collector (
  session_id VARBINARY(20) NOT NULL,
  -- session[52dd54f1-28a1-4b84-6bd4-fc45fd9f3b78]52fc6d14-1c07-ffcd-107c-7132b2d263b0"
  ref_string VARCHAR(128) NOT NULL,
  ts_created BIGINT(20) NOT NULL,
  CONSTRAINT server_session
    FOREIGN KEY ehc_session (session_id)
    REFERENCES vcenter_session (session_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE object (
  uuid VARBINARY(20) NOT NULL, -- sha1(vcenter_uuid + moref)
  vcenter_uuid VARBINARY(16) NOT NULL,
  -- Hint: 180 Bytes might seem overkill for MoRefs, but there are:
  --   52d4e949-55c225c2923-a7ba-009689221ad9-datastorebrowser (ESXi)
  --   HaOpaqueNetwork-nsx.LogicalSwitch-9d2b175b-10d2-4bfe-1896-fca2215b6765 (NSX-T)
  --   DVPG-6e 0f 0c 50 75 de 07 43-9e 0c 3a e7 df f6 0c 4f-dvportgroup-1296 (DVS)
  moref VARCHAR(128) NOT NULL, -- textual id
  object_name VARCHAR(255) NOT NULL,
  object_type ENUM(
    'ComputeResource',
    'ClusterComputeResource',
    'Datacenter',
    'Datastore',
    'DatastoreHostMount',
    'DistributedVirtualPortgroup',
    'DistributedVirtualSwitch',
    'Folder',
    'HostMountInfo',
    'HostSystem',
    'Network',
    'OpaqueNetwork',
    'ResourcePool',
    'StoragePod',
    'VirtualApp',
    'VirtualMachine',
    'VmwareDistributedVirtualSwitch'
  ) NOT NULL,
  overall_status ENUM(
     'gray',
     'green',
     'yellow',
     'red'
  ) NOT NULL,
  level TINYINT UNSIGNED NOT NULL,
  parent_uuid VARBINARY(20) DEFAULT NULL,
  PRIMARY KEY(uuid),
  UNIQUE KEY vcenter_moref (vcenter_uuid, moref),
  INDEX object_type (object_type),
  INDEX object_name (object_name(64)),
  CONSTRAINT object_parent
    FOREIGN KEY parent (parent_uuid)
    REFERENCES object (uuid)
      ON DELETE RESTRICT
      ON UPDATE RESTRICT,
  CONSTRAINT object_vcenter
    FOREIGN KEY object_vcenter_uuid (vcenter_uuid)
    REFERENCES vcenter (instance_uuid)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE compute_resource (
  uuid VARBINARY(20) NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  effective_cpu_mhz INT UNSIGNED NOT NULL,
  effective_memory_size_mb BIGINT(20) UNSIGNED NOT NULL,
  cpu_cores SMALLINT UNSIGNED NOT NULL,
  cpu_threads SMALLINT UNSIGNED NOT NULL,
  effective_hosts MEDIUMINT UNSIGNED NOT NULL,
  hosts MEDIUMINT UNSIGNED NOT NULL,
  total_memory_size_mb BIGINT(10) UNSIGNED NOT NULL,
  total_cpu_mhz INT UNSIGNED NOT NULL,
  PRIMARY KEY(uuid),
  UNIQUE INDEX vcenter_uuid_uuid (vcenter_uuid, uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE host_system (
  uuid VARBINARY(20) NOT NULL,
  host_name VARCHAR(255) DEFAULT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  product_api_version VARCHAR(32) NOT NULL, -- 6.0
  product_full_name VARCHAR(64) NOT NULL,   -- VMware ESXi 6.0.0 build-5572656
  bios_version VARCHAR(64) DEFAULT NULL, -- P89, SE5C610.86B.01.01.0020.122820161512
  bios_release_date DATETIME DEFAULT NULL, -- 2017-02-17T00:00:00Z
  sysinfo_vendor VARCHAR(64) DEFAULT NULL, -- HP
  sysinfo_model VARCHAR(64) DEFAULT NULL,  -- ProLiant DL380 Gen9
  sysinfo_uuid VARCHAR(64) NOT NULL,   -- 30133937-3365-54a3-3544-30374a334d53
  service_tag VARCHAR(64) DEFAULT NULL, -- DQ7EXJ1 or VMware-42 12 34 56 78 9a bc de-fd cb a9 87 65 43 21 00
  hardware_cpu_model VARCHAR(64) NOT NULL, -- Intel(R) Xeon(R) CPU E5-2699A v4 @ 2.40GHz
  hardware_cpu_mhz INT UNSIGNED NOT NULL,
  hardware_cpu_packages SMALLINT UNSIGNED NOT NULL,
  hardware_cpu_cores SMALLINT UNSIGNED NOT NULL,
  hardware_cpu_threads SMALLINT UNSIGNED NOT NULL,
  hardware_memory_size_mb INT(10) UNSIGNED NOT NULL,
  hardware_num_hba SMALLINT UNSIGNED NOT NULL,
  hardware_num_nic SMALLINT UNSIGNED NOT NULL,
  runtime_power_state ENUM(
    'poweredOff',
    'poweredOn',
    'standBy',
    'unknown'
  ) NOT NULL,
  das_host_state ENUM(
    'connectedToMaster',
    'election',
    'fdmUnreachable',
    'hostDown',
    'initializationError',
    'master',
    'networkIsolated',
    'networkPartitionedFromMaster',
    'uninitializationError',
    'uninitialized'
  ) DEFAULT NULL,
  custom_values TEXT DEFAULT NULL,
  PRIMARY KEY(uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE host_pci_device (
  id VARCHAR(16) NOT NULL, -- bus:slot.function . But: 0000:00:00.0
  host_uuid  VARBINARY(20) NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  bus BINARY(1) NOT NULL, -- byte
  slot BINARY(1) NOT NULL, -- byte
  device_function BINARY(1) NOT NULL, -- byte
  class_id SMALLINT NOT NULL, -- short
  device_id SMALLINT NOT NULL, -- short
  device_name VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  sub_device_id SMALLINT NOT NULL, -- short
  vendor_id SMALLINT NOT NULL, -- short
  vendor_name VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  sub_vendor_id SMALLINT NOT NULL, -- short
  parent_bridge VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY(host_uuid, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE host_sensor (
  name VARCHAR(128) NOT NULL,
  host_uuid  VARBINARY(20) NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  health_state ENUM(
    'green',
    'yellow',
    'unknown',
    'red'
  ) NOT NULL,
  current_reading INT NOT NULL,
  unit_modifier SMALLINT NOT NULL,
  base_units VARCHAR(32) DEFAULT NULL,
  rate_units VARCHAR(32) DEFAULT NULL,
  sensor_type VARCHAR(32) NOT NULL,
  -- Seen so far:
  -- fan, power, system, temperature, voltage, other,
  -- Battery, Cable/Interconnect, Chassis, Management Subsystem Health,
  -- Memory, Processors, Software Components, Storage, System, Watchdog
  PRIMARY KEY(host_uuid, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE host_physical_nic (
  host_uuid VARBINARY(20) NOT NULL,
  nic_key VARCHAR(64) NOT NULL,
  auto_negotiate_supported ENUM ('y', 'n') DEFAULT NULL,
  device VARCHAR(128) NOT NULL,
  driver VARCHAR(128) DEFAULT NULL,
  link_speed_mb INT(10) UNSIGNED DEFAULT NULL,
  link_duplex ENUM ('y', 'n') DEFAULT NULL,
  mac_address VARCHAR(17) DEFAULT NULL, -- binary(6)? new xxeuid?
  pci VARCHAR(12) DEFAULT NULL, -- 0000:38:00.1
  vcenter_uuid VARBINARY(16) NOT NULL,
  PRIMARY KEY(host_uuid, nic_key),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE host_virtual_nic (
  host_uuid VARBINARY(20) NOT NULL,
  nic_key VARCHAR(64) NOT NULL,
  net_stack_instance_key VARCHAR(128) DEFAULT NULL,
  port VARCHAR(128) DEFAULT NULL,
  portgroup VARCHAR(128) DEFAULT NULL,
  mac_address VARCHAR(17) DEFAULT NULL,
  mtu INT(10) DEFAULT NULL,
  ipv4_address VARCHAR(15) DEFAULT NULL,
  ipv4_subnet_mask VARCHAR(15) DEFAULT NULL,
  ipv6_address VARCHAR(47) DEFAULT NULL,
  ipv6_prefic_length INT(10) DEFAULT NULL,
  ipv6_dad_state ENUM (
    'deprecated',
    'duplicate',
    'inaccessible',
    'invalid',
    'preferred',
    'tentative',
    'unknown'
  ) DEFAULT NULL,
  ipv6_origin ENUM (
    'dhcp',
    'linklayer',
    'manual',
    'other',
    'random'
  ) DEFAULT NULL,
  -- distributedVirtualPort:
  dv_connection_cookie INT(10) DEFAULT NULL,
  dv_portgroup_key VARCHAR(128) DEFAULT NULL, -- dvportgroup-34067
  dv_port_key VARCHAR(128) DEFAULT NULL, -- 7
  dv_switch_uuid VARCHAR(47) DEFAULT NULL, --  50 1e be 8f 5e 26 a5 66-4e c9 30 14 ac 58 a0 1c
  device VARCHAR(128) DEFAULT NULL,
  tso_enabled ENUM ('y', 'n') DEFAULT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  PRIMARY KEY(host_uuid, nic_key),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE host_list (
  list_checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (list_checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE host_list_member (
  list_checksum VARBINARY(20) NOT NULL, -- sha1(uuid[uuid..])
  uuid VARBINARY(20) NOT NULL,
  PRIMARY KEY (uuid, list_checksum),
  CONSTRAINT host_list_member_list
    FOREIGN KEY host_list (list_checksum)
    REFERENCES host_list (list_checksum)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE virtual_machine (
  uuid  VARBINARY(20) NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  hardware_memorymb INT UNSIGNED NULL DEFAULT NULL,
  hardware_numcpu TINYINT UNSIGNED NULL DEFAULT NULL,
  hardware_numcorespersocket TINYINT UNSIGNED NULL DEFAULT 1,
  template ENUM('y', 'n') NULL DEFAULT NULL, -- TODO: drop and skip templates? Or separate table?
  instance_uuid VARCHAR(64) DEFAULT NULL,   -- 5004890e-8edd-fe5f-d116-d5704b2043e4
  bios_uuid VARCHAR(64) DEFAULT NULL,       -- 42042ce7-1c4f-b339-2293-40357f1d6860
  version VARCHAR(32) NULL DEFAULT NULL,         -- vmx-11
  online_standby ENUM('y', 'n') NULL DEFAULT NULL,
  paused ENUM('y', 'n') NULL DEFAULT NULL,
  cpu_hot_add_enabled ENUM('y', 'n') NULL DEFAULT NULL,
  memory_hot_add_enabled ENUM('y', 'n') NULL DEFAULT NULL,
  connection_state ENUM (
    'connected',    -- server has access to the vm
    'disconnected', -- disconnected from the virtual machine, since its host is disconnected
    'inaccessible', -- vm config unaccessible
    'invalid',      -- vm config is invalid
    'orphaned'      -- vm no longer exists on host (but in vCenter)
  ) NULL DEFAULT NULL,
  guest_state ENUM (
    'notRunning',
    'resetting',
    'running',
    'shuttingDown',
    'standby',
    'unknown'
  ) NULL DEFAULT NULL,
  guest_tools_status ENUM (
    'toolsNotInstalled',
    'toolsNotRunning',
    'toolsOld',
    'toolsOk'
  ) DEFAULT NULL,
  guest_tools_running_status ENUM (
    'guestToolsNotRunning',
    'guestToolsRunning',
    'guestToolsExecutingScripts' -- VMware Tools is starting.
  ) NULL DEFAULT NULL,
  guest_tools_version VARCHAR(32) DEFAULT NULL, -- 10338
  guest_id VARCHAR(64) DEFAULT NULL,        -- rhel7_64Guest
  -- Linux 3.10.0-693.17.1.el7.x86_64 CentOS Linux release 7.4.1708 (Core)
  guest_full_name VARCHAR(255) DEFAULT NULL, -- Red Hat Enterprise Linux 7 (64-bit)
  guest_host_name VARCHAR(255) DEFAULT NULL,
  guest_ip_address VARCHAR(50) DEFAULT NULL,
  resource_pool_uuid VARBINARY(20) DEFAULT NULL,
  runtime_host_uuid VARBINARY(20) DEFAULT NULL,
  runtime_last_boot_time DATETIME DEFAULT NULL, -- TODO: to BIGINT?
  runtime_last_suspend_time DATETIME DEFAULT NULL, -- TODO: to BIGINT?
  runtime_power_state ENUM (
      'poweredOff',
      'poweredOn',
      'suspended'
  ) NOT NULL,
  boot_network_protocol ENUM('ipv4', 'ipv6') DEFAULT NULL,
  boot_order VARCHAR(128) DEFAULT NULL,
  custom_values TEXT DEFAULT NULL,
  annotation TEXT DEFAULT NULL,
  PRIMARY KEY(uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vm_list (
  list_checksum VARBINARY(20) NOT NULL,
  PRIMARY KEY (list_checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vm_list_member (
  list_checksum VARBINARY(20) NOT NULL, -- sha1(uuid[uuid..])
  uuid VARBINARY(20) NOT NULL,
  PRIMARY KEY (uuid, list_checksum),
  CONSTRAINT vm_list_member_list
    FOREIGN KEY vm_list (list_checksum)
    REFERENCES vm_list (list_checksum)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE storage_pod (
  uuid VARBINARY(20) NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  pod_name VARCHAR(255) DEFAULT NULL,
  free_space BIGINT UNSIGNED NOT NULL,
  capacity BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY(uuid),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE distributed_virtual_switch (
  uuid VARBINARY(20) NOT NULL,
  num_hosts INT(10) NOT NULL,
  num_ports INT(10) NOT NULL,
  max_ports INT(10) NOT NULL,
  hostmembers_checksum VARBINARY(20) DEFAULT NULL,
  portgroups_checksum VARBINARY(20) DEFAULT NULL,
  vms_checksum VARBINARY(20) DEFAULT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  description TEXT DEFAULT NULL,
  PRIMARY KEY(uuid),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE distributed_virtual_portgroup (
  uuid VARBINARY(20) NOT NULL,
  portgroup_type ENUM (
    'earlyBinding', -- assigned when reconfigured
    'ephemeral',    -- assigned when powered on
    'lateBinding'   -- Deprecated as of vSphere API 5.0.
  ) NOT NULL,
  vlan INT(10) DEFAULT NULL,
  vlan_ranges VARCHAR(255) DEFAULT NULL,
  num_ports INT(10) NOT NULL,
  distributed_virtual_switch_uuid VARBINARY(20) NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  PRIMARY KEY(uuid),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE datastore (
  uuid VARBINARY(20) NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  maintenance_mode ENUM(
      'normal',
      'enteringMaintenance',
      'inMaintenance'
  ) DEFAULT NULL,
  is_accessible ENUM('y', 'n') NOT NULL,
  capacity BIGINT(20) UNSIGNED DEFAULT NULL,
  free_space BIGINT(20) UNSIGNED DEFAULT NULL,
  uncommitted BIGINT(20) UNSIGNED DEFAULT NULL,
  multiple_host_access ENUM('y', 'n') DEFAULT NULL,
  ts_last_forced_refresh BIGINT(20) DEFAULT NULL,
  -- datastore_type ENUM(
  --     'vmfs', -- VMFS??
  --     'nfs',
  --     'cifs'
  -- ) NOT NULL,
  PRIMARY KEY(uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vm_snapshot (
  uuid VARBINARY(20) NOT NULL,
  parent_uuid VARBINARY(20) DEFAULT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  vm_uuid VARBINARY(20) NOT NULL,
  id INT UNSIGNED NOT NULL,
  moref VARCHAR(32) NOT NULL, -- textual id, we have no object entry
  name VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  ts_create BIGINT NOT NULL,
  state ENUM (
    'poweredOff',
    'poweredOn',
    'suspended'
  ) NOT NULL, -- VM power state when the snapshot was taken
  quiesced ENUM('y', 'n') NOT NULL,
  PRIMARY KEY (uuid),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vm_datastore_usage (
  vm_uuid VARBINARY(20) NOT NULL,
  datastore_uuid  VARBINARY(20) NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  committed BIGINT(20) UNSIGNED DEFAULT NULL,
  uncommitted BIGINT(20) UNSIGNED DEFAULT NULL,
  unshared BIGINT(20) UNSIGNED DEFAULT NULL,
  ts_updated BIGINT(20) UNSIGNED DEFAULT NULL,
  PRIMARY KEY(vm_uuid, datastore_uuid),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vm_hardware (
  vm_uuid VARBINARY(20) NOT NULL,
  hardware_key INT(10) UNSIGNED NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  bus_number INT(10) UNSIGNED DEFAULT NULL,
  unit_number INT(10) UNSIGNED DEFAULT NULL, -- unit number of this device on its controller
  controller_key INT(10) UNSIGNED DEFAULT NULL,
  label VARCHAR(64) NOT NULL,
  summary VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY(vm_uuid, hardware_key),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vm_disk (
  vm_uuid VARBINARY(20) NOT NULL,
  hardware_key INT(10) UNSIGNED NOT NULL,
  disk_uuid VARBINARY(16) DEFAULT NULL, -- backing->uuid: 6000C272-5a6b-ca2f-1706-4d2493ba11f0
  datastore_uuid VARBINARY(20) DEFAULT NULL, -- backing->datastore->_
  file_name VARCHAR(255) DEFAULT NULL, -- backing->fileName: [DSNAME] <name>/<name>.vmdk
  capacity BIGINT(20) UNSIGNED DEFAULT NULL, -- capacityInBytes
  disk_mode ENUM(
    'persistent', -- Changes are immediately and permanently written to the virtual disk.
    'nonpersistent', -- Changes to virtual disk are made to a redo log and discarded at power off.
    'undoable', -- Changes are made to a redo log, but you are given the option to commit or undo.
    'independent_persistent', -- Same as persistent, but not affected by snapshots.
    'independent_nonpersistent', -- Same as nonpersistent, but not affected by snapshots.
    'append' -- Changes are appended to the redo log; you revoke changes by removing the undo log.
  ) NOT NULL, -- backing->diskMode
  split ENUM ('y', 'n') DEFAULT NULL, --  Flag to indicate the type of virtual disk file: split or monolithic.
                                  -- If true, the virtual disk is stored in multiple files, each 2GB.
  write_through ENUM ('y', 'n') DEFAULT NULL,
  thin_provisioned ENUM ('y', 'n') DEFAULT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  PRIMARY KEY(vm_uuid, hardware_key),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vm_disk_usage (
  vm_uuid VARBINARY(20) NOT NULL,
  disk_path VARCHAR(128) NOT NULL,
  capacity BIGINT(20) NOT NULL,
  free_space BIGINT(20) NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  PRIMARY KEY(vm_uuid, disk_path),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vm_network_adapter (
  vm_uuid VARBINARY(20) NOT NULL,
  hardware_key INT(10) UNSIGNED NOT NULL,
  portgroup_uuid VARBINARY(20) DEFAULT NULL, -- port->portgroupKey (moid, dvportgroup-1288720)
  port_key VARCHAR(64) DEFAULT NULL, -- port->portKey Can be 'c-31'
  mac_address VARCHAR(17) DEFAULT NULL, -- binary(6)? new xxeuid?
  address_type ENUM(
    'manual',    -- Statically assigned MAC address
    'generated', -- Automatically generated MAC address
    'assigned'   -- MAC address assigned by VirtualCenter
  ) NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  PRIMARY KEY(vm_uuid, hardware_key),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE host_quick_stats (
  uuid VARBINARY(20) NOT NULL,
  distributed_cpu_fairness INT(10) DEFAULT NULL,
  distributed_memory_fairness INT(10) DEFAULT NULL,
  overall_cpu_usage INT(10) UNSIGNED DEFAULT NULL,
  overall_memory_usage_mb INT(10) UNSIGNED DEFAULT NULL,
  uptime INT(10) UNSIGNED DEFAULT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  PRIMARY KEY(uuid),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vm_quick_stats (
  uuid VARBINARY(20) NOT NULL,
  ballooned_memory_mb INT(10) UNSIGNED DEFAULT NULL,
  compressed_memory_kb BIGINT(20) UNSIGNED DEFAULT NULL,
  consumed_overhead_memory_mb INT(10) UNSIGNED DEFAULT NULL,
  distributed_cpu_entitlement INT(10) UNSIGNED DEFAULT NULL, -- mhz
  distributed_memory_entitlement_mb INT(10) UNSIGNED DEFAULT NULL,
  ft_latency_status ENUM('gray', 'green', 'red', 'yellow') DEFAULT NULL,
  ft_log_bandwidth INT(10) DEFAULT NULL, -- Hint -1 -> NULL
  ft_secondary_latency INT(10) DEFAULT NULL, -- Hint -1 -> NULL
  guest_heartbeat_status ENUM('gray', 'green', 'red', 'yellow') NOT NULL,
  guest_memory_usage_mb INT(10) UNSIGNED DEFAULT NULL,
  host_memory_usage_mb INT(10) UNSIGNED DEFAULT NULL,
  overall_cpu_demand INT(10) UNSIGNED DEFAULT NULL, -- mhz
  overall_cpu_usage INT(10) UNSIGNED DEFAULT NULL, -- mhz
  private_memory_mb INT(10) UNSIGNED DEFAULT NULL,
  shared_memory_mb INT(10) UNSIGNED DEFAULT NULL,
  ssd_swapped_memory_kb BIGINT(10) UNSIGNED DEFAULT NULL,
  static_cpu_entitlement INT(10) UNSIGNED DEFAULT NULL, -- mhz
  static_memory_entitlement_mb INT(10) UNSIGNED DEFAULT NULL,
  swapped_memory_mb INT(10) UNSIGNED DEFAULT NULL,
  uptime INT(10) UNSIGNED DEFAULT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  PRIMARY KEY(uuid),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE alarm_history (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  ts_event_ms BIGINT(20) NOT NULL,
  event_type ENUM (
    'AlarmAcknowledgedEvent',
    'AlarmClearedEvent',
    'AlarmCreatedEvent',
    'AlarmReconfiguredEvent',
    'AlarmRemovedEvent',
    'AlarmStatusChangedEvent'
  ) NOT NULL,
  event_key BIGINT(20) UNSIGNED NOT NULL,
  event_chain_id BIGINT(20) UNSIGNED NOT NULL,
  entity_uuid VARBINARY(20) DEFAULT NULL,
  source_uuid VARBINARY(20) DEFAULT NULL,
  alarm_name VARCHAR(255) DEFAULT NULL,
  alarm_moref VARCHAR(48) DEFAULT NULL,
  status_from ENUM(
    'gray',
    'green',
    'yellow',
    'red'
  ) DEFAULT NULL,
  status_to ENUM(
    'gray',
    'green',
    'yellow',
    'red'
  ) DEFAULT NULL,
  full_message MEDIUMTEXT DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX time_idx (ts_event_ms),
  INDEX search_type_idx (event_type, ts_event_ms),
  INDEX search_entity_idx (entity_uuid, ts_event_ms)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vm_event_history (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  ts_event_ms BIGINT(20) NOT NULL,
  event_type ENUM (
    'VmBeingMigratedEvent',
    'VmBeingHotMigratedEvent',
    'VmEmigratingEvent',
    'VmFailedMigrateEvent',
    'VmMigratedEvent',
    'DrsVmMigratedEvent',
    'VmBeingCreatedEvent',
    'VmCreatedEvent',
    'VmStartingEvent',
    'VmPoweredOnEvent',
    'VmPoweredOffEvent',
    'VmResettingEvent',
    'VmSuspendedEvent',
    'VmStoppingEvent',
    'VmBeingDeployedEvent',
    'VmReconfiguredEvent',
    'VmBeingClonedEvent',
    'VmBeingClonedNoFolderEvent',
    'VmClonedEvent',
    'VmCloneFailedEvent'
  ) NOT NULL,
  event_key BIGINT(20) UNSIGNED NOT NULL,
  event_chain_id BIGINT(20) UNSIGNED NOT NULL,
  is_template ENUM('y', 'n') DEFAULT NULL,
  datacenter_uuid VARBINARY(20) DEFAULT NULL,
  compute_resource_uuid VARBINARY(20) DEFAULT NULL,
  host_uuid VARBINARY(20) DEFAULT NULL,
  vm_uuid VARBINARY(20) DEFAULT NULL,
  datastore_uuid VARBINARY(20) DEFAULT NULL,
  dvs_uuid VARBINARY(20) DEFAULT NULL,
  destination_host_uuid VARBINARY(20) DEFAULT NULL,
  destination_datacenter_uuid VARBINARY(20) DEFAULT NULL,
  destination_datastore_uuid VARBINARY(20) DEFAULT NULL,
  full_message MEDIUMTEXT DEFAULT NULL,
  user_name VARCHAR(128) DEFAULT NULL,
  fault_message TEXT DEFAULT NULL,
  fault_reason TEXT DEFAULT NULL,
  config_spec MEDIUMTEXT DEFAULT NULL,
  config_changes MEDIUMTEXT DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX time_idx (ts_event_ms),
  INDEX search_type_idx (event_type, ts_event_ms),
  INDEX search_host_idx (host_uuid, ts_event_ms),
  INDEX search_vm_idx (vm_uuid, ts_event_ms),
  INDEX search_ds_idx (datastore_uuid, ts_event_ms)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE monitoring_connection (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  priority SMALLINT(5) UNSIGNED NOT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  source_type ENUM (
      'ido',
      'icinga2-api',
      'icingadb'
  ) NOT NULL,
  source_resource_name VARCHAR(64) DEFAULT NULL, -- null means default resource
  host_property VARCHAR(128) DEFAULT NULL,
  monitoring_host_property VARCHAR(128) DEFAULT NULL,
  vm_property VARCHAR(128) DEFAULT NULL,
  monitoring_vm_host_property VARCHAR(128) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE host_monitoring_hoststate (
  host_uuid VARBINARY(20) NOT NULL,
  ido_connection_id INT(10) UNSIGNED NOT NULL,
  icinga_object_id BIGINT(20) NOT NULL,
  current_state ENUM(
    'UP',
    'DOWN',
    'UNREACHABLE',
    'MISSING'
  ) NOT NULL,
  PRIMARY KEY (host_uuid),
  INDEX sync_idx (ido_connection_id),
  CONSTRAINT host_monitoring_host
    FOREIGN KEY host_uuid (host_uuid)
    REFERENCES host_system (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE host_monitoring_property (
  host_uuid VARBINARY(20) NOT NULL,
  property_name VARCHAR(64) NOT NULL,
  property_value VARCHAR(255) NOT NULL,
  PRIMARY KEY (host_uuid, property_name),
  CONSTRAINT host_monitoring_property_host
    FOREIGN KEY host_uuid (host_uuid)
    REFERENCES host_system (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vm_monitoring_hoststate (
  vm_uuid VARBINARY(20) NOT NULL,
  ido_connection_id INT(10) UNSIGNED NOT NULL,
  icinga_object_id BIGINT(20) NOT NULL,
  current_state ENUM(
    'UP',
    'DOWN',
    'UNREACHABLE',
    'MISSING'
  ) NOT NULL,
  PRIMARY KEY (vm_uuid),
  INDEX sync_idx (ido_connection_id),
  CONSTRAINT vm_monitoring_host
    FOREIGN KEY vm_uuid (vm_uuid)
    REFERENCES virtual_machine (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE vm_monitoring_property (
  vm_uuid VARBINARY(20) NOT NULL,
  property_name VARCHAR(64) NOT NULL,
  property_value VARCHAR(255) NOT NULL,
  PRIMARY KEY (vm_uuid, property_name),
  CONSTRAINT vm_monitoring_property_vm
    FOREIGN KEY vm_uuid (vm_uuid)
    REFERENCES virtual_machine (uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

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
  label VARCHAR(96) NOT NULL,
  group_name VARCHAR(32) NOT NULL,
  unit_name VARCHAR(32) NOT NULL,
  summary VARCHAR(255) NOT NULL,
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

CREATE TABLE counter_300x5 (
  object_uuid VARBINARY(20) NOT NULL,
  counter_key INT UNSIGNED NOT NULL,
  instance VARCHAR(64) NOT NULL,
  ts_last BIGINT NOT NULL,
  value_last BIGINT NOT NULL,
  value_minus1 BIGINT DEFAULT NULL,
  value_minus2 BIGINT DEFAULT NULL,
  value_minus3 BIGINT DEFAULT NULL,
  value_minus4 BIGINT DEFAULT NULL,
  vcenter_uuid VARBINARY(16) NOT NULL,
  PRIMARY KEY (object_uuid, counter_key, instance),
  INDEX vcenter_uuid (vcenter_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

-- Not yet:
-- CREATE TABLE vm_triggered_alarm (
--   id BIGINT(20) UNSIGNED NOT NULL,
--   object_id INT(10) UNSIGNED NOT NULL,
-- );

-- CREATE TABLE vm_alarm_history (
--   vm_id BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
-- );

INSERT INTO vspheredb_schema_migration
  (schema_version, migration_time)
VALUES (31, NOW());
