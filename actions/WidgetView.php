<?php declare(strict_types = 0);

namespace Modules\NewZabbixItemGrid\Actions;

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {

    protected function doAction(): void {
        $itemids = $this->fields_values['itemids'] ?? [];    
        $items = [];
        $history_data = [];
        $json_names = $this->fields_values['custom_names_json'] ?? '{}';
        $custom_labels = json_decode($json_names, true) ?: [];

        $parser = new \CRelativeTimeParser();
        $from = $this->getInput('from', $this->fields_values['time_period']['from'] ?? 'now-1h');
        $to = $this->getInput('to', $this->fields_values['time_period']['to'] ?? 'now');
        
        $from_ts = time() - 3600;
        if ($from !== '' && $parser->parse($from) === \CParser::PARSE_SUCCESS) {
            $from_ts = $parser->getDateTime(true)->getTimestamp();
        } elseif ($from !== '') {
            $from_ts = strtotime($from) ?: $from_ts;
        }
        
        $to_ts = time();
        if ($to !== '' && $parser->parse($to) === \CParser::PARSE_SUCCESS) {
            $to_ts = $parser->getDateTime(false)->getTimestamp();
        } elseif ($to !== '') {
            $to_ts = strtotime($to) ?: $to_ts;
        }

        $period = $to_ts - $from_ts;
        $use_trends = ($period > 259200); // More than 3 days

        if ($itemids) {
            $items = \API::Item()->get([
                'output' => ['itemid', 'name', 'lastvalue', 'lastclock', 'value_type', 'units'],
                'selectHosts' => ['name'],
                'selectValueMap' => ['mappings'],
                'itemids' => $itemids,
                'preservekeys' => true
            ]);

            foreach ($items as $itemid => $item) {
                if ($use_trends && ($item['value_type'] == ITEM_VALUE_TYPE_FLOAT || $item['value_type'] == ITEM_VALUE_TYPE_UINT64)) {
                    $hist = \API::Trend()->get([
                        'itemids'   => $itemid,
                        'time_from' => $from_ts,
                        'time_till' => $to_ts,
                        'sortfield' => 'clock',
                        'sortorder' => 'ASC'
                    ]);
                    // Map trends to look like history for the view
                    foreach ($hist as &$h) {
                        $h['value'] = $h['value_avg'];
                    }
                } else {
                    $hist = \API::History()->get([
                        'history'   => $item['value_type'],
                        'itemids'   => $itemid,
                        'time_from' => $from_ts,
                        'time_till' => $to_ts,
                        'sortfield' => 'clock',
                        'sortorder' => 'ASC',
                        'limit'     => 5000
                    ]);
                }
                $history_data[$itemid] = $hist;
            }
        }

        $this->setResponse(new CControllerResponseData([
            'name' => $this->getInput('name', $this->widget->getName()),
            'user' => [
                'debug_mode' => $this->getDebugMode()
            ],     
            'items_data' => $items,
            'custom_labels' => $custom_labels,
            'from_ts' => $from_ts,
            'to_ts' => $to_ts,
            'show_status' => (bool)($this->fields_values['show_status'] ?? 1),
            'grid_count' => (int)($this->fields_values['grid_count'] ?? 4),
            'history_data' => $history_data
        ]));
    }
}
