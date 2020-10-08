<?php

namespace Icinga\Module\Vspheredb\Sync;

use Exception;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\MappedClass\PerfCounterInfo;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class SyncPerfCounterInfo
{
    use LoggerAwareTrait;

    /** @var VCenter */
    protected $vCenter;

    public function __construct(VCenter $vCenter, LoggerInterface $logger)
    {
        $this->vCenter = $vCenter;
        $this->setLogger($logger);
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws Exception
     */
    public function run()
    {
        $this->processCounterInfo(
            $this->vCenter
                ->getApi($this->logger)
                ->perfManager()
                ->getPerformanceManager()
                ->getPerfCounter()
        );
    }

    /**
     * TODO: really sync
     *
     * @param PerfCounterInfo[] $infos
     * @throws Exception
     */
    protected function processCounterInfo($infos)
    {
        $uuid = $this->vCenter->get('uuid');
        $db = $this->vCenter->getDb();
        $units = [];
        $groups = [];
        $data = [];
        $this->logger->debug('Preparing Counters for DB');
        foreach ($infos as $info) {
            $name  = $info->nameInfo->key;
            $group = $info->groupInfo->key;
            $unit  = $info->unitInfo->key;

            if (! array_key_exists($group, $groups)) {
                $groups[$group] = [
                    'vcenter_uuid' => $uuid,
                    'name'    => $group,
                    'label'   => $info->groupInfo->label,
                    'summary' => $info->groupInfo->summary,
                ];
            }
            if (! array_key_exists($unit, $units)) {
                $units[$unit] = [
                    'vcenter_uuid' => $uuid,
                    'name'    => $unit,
                    'label'   => $info->unitInfo->label,
                    'summary' => $info->unitInfo->summary,
                ];
            }
            $current = [
                'vcenter_uuid'     => $uuid,
                'counter_key'      => $info->key,
                'name'             => $name,
                'group_name'       => $group,
                'unit_name'        => $unit,
                'label'            => $info->nameInfo->label,
                'summary'          => $info->nameInfo->summary,
                'rollup_type'      => (string) $info->rollupType,
                'stats_type'       => (string) $info->statsType,
                'level'            => isset($info->level) ? $info->level : 0, // ESXi? Check docs!
                'per_device_level' => isset($info->perDeviceLevel) ? $info->perDeviceLevel : 0,
            ];
            $data[] = $current;
        }

        $this->logger->debug('Ready to store Counters to DB');
        // This is not a full sync, but good enough for our needs.
        $db->beginTransaction();
        $existingGroups = $db->fetchPairs(
            $db->select()
            ->from('performance_group', ['name', '(1)'])
            ->where('vcenter_uuid = ?', $uuid)
        );
        $existingUnits = $db->fetchPairs(
            $db->select()
            ->from('performance_unit', ['name', '(1)'])
            ->where('vcenter_uuid = ?', $uuid)
        );
        $existingCounters = $db->fetchPairs(
            $db->select()
            ->from('performance_counter', ['counter_key', '(1)'])
            ->where('vcenter_uuid = ?', $uuid)
        );
        $cnt = 0;
        try {
            foreach ($groups as $group) {
                if (! isset($existingGroups[$group['name']])) {
                    $db->insert('performance_group', $group);
                    $cnt++;
                }
            }
            foreach ($units as $unit) {
                if (! isset($existingUnits[$unit['name']])) {
                    $db->insert('performance_unit', $unit);
                    $cnt++;
                }
            }
            foreach ($data as $info) {
                if (! isset($existingCounters[$info['counter_key']])) {
                    $db->insert('performance_counter', $info);
                    $cnt++;
                }
            }
            $db->commit();
        } catch (Exception $error) {
            try {
                $db->rollBack();
            } catch (Exception $rollBackError) {
                // There is nothing we can do.
            }

            throw $error;
        }
        if ($cnt > 0) {
            $this->logger->info(sprintf('%d counter-related changes stored to DB', $cnt));
        }
    }

    protected function processHistoricalIntervals($intervals)
    {
        // Currently unused.
        $db = $this->vCenter->getDb();
        foreach ($intervals as $interval) {
            $db->insert('performance_interval', [
                'name'    => $interval->key, // 1 ... 4
                'sampling_period' => $interval->samplingPeriod, // 300 ... 86400
                'label'   => $interval->name, // 'Past day' ... 'Past year'
                'length'  => $interval->length, //  86400 ... 31536000
                'level'   => $interval->level, // reserved name! 1, 2...
                'enabled' => $interval->enabled, // 1/0 to y/n
            ]);
        }
    }
}
