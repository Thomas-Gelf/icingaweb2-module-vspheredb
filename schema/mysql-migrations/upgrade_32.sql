SET @stmt = (SELECT IF(
                            (SELECT EXISTS(
                                            SELECT * FROM information_schema.table_constraints
                                            WHERE
                                                    table_schema   = DATABASE()
                                              AND table_name = 'monitoring_connection'
                                              AND constraint_name = 'monitoring_vcenter'
                                        )),
                            'ALTER TABLE monitoring_connection DROP FOREIGN KEY monitoring_vcenter',
                            'SELECT 1'
                        ));
ALTER TABLE `table_name`
    DROP FOREIGN KEY `id_name_fk`;
ALTER TABLE `table_name`
    DROP INDEX  `id_name_fk`;

PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @stmt = NULL;

INSERT INTO vspheredb_schema_migration
(schema_version, migration_time)
VALUES (32, NOW());
