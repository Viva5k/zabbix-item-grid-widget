<?php declare(strict_types = 0);

/**
 * @var CView $this
 * @var array $data
 */

$convert_units = function($value, $unit) {
    if (!is_numeric($value)) {
        return ['value' => $value, 'unit' => $unit];
    }

    $val = (float)$value;
    $abs_val = abs($val);
    $clean_unit = trim($unit);
    
    $is_time = ($clean_unit === 's' || $clean_unit === 'uptime' || $clean_unit === 'unixtime');
    if ($is_time) {
        if ($clean_unit === 'unixtime') {
            return ['value' => date("Y-m-d H:i:s", (int)$val), 'unit' => ''];
        }
        return ['value' => convertUnitsS($val), 'unit' => ''];
    }
    
    $units_to_convert = ['B', 'b', 'bps', 'Bps', 'Bytes', 'bytes'];

    if (in_array($clean_unit, $units_to_convert) && $abs_val >= 1024) {
        $prefixes = ['','K','M','G','T','P'];
        $power = min(floor(log($abs_val, 1024)), count($prefixes) - 1);
        $new_val = round($val / pow(1024, $power), 1);
        $new_unit = $prefixes[$power] . $clean_unit;
        return ['value' => $new_val, 'unit' => $new_unit];
    }

    return ['value' => round($val, 1), 'unit' => $unit];
};

// Хелпер для определения цвета по порогам
$get_threshold_color = function($current_val, $max_val) {
    $current = (float)$current_val;
    $max = (float)$max_val;
    if ($max <= 0) return '#10B981'; // Green

    $percentage = ($current / $max) * 100;
    if ($percentage >= 90) return '#EF4444'; // Red
    if ($percentage >= 70) return '#F59E0B'; // Yellow
    return '#10B981'; // Green
};

$render_sparkline = function($itemid, $max_val, $height = 30) use ($data, $get_threshold_color) {
    if (!isset($data['history_data'][$itemid]) || empty($data['history_data'][$itemid])) {
        return null;
    }

    $history = $data['history_data'][$itemid];
    $values = array_column($history, 'value');
    $clocks = array_column($history, 'clock');
    $ts_start = (int)$data['from_ts'];
    $ts_end = (int)$data['to_ts'];
    $duration = max(1, $ts_end - $ts_start);
    
    $width = 250;
    $padding = 2;
    $count = count($values);
    if ($count < 2) return null;

    $min_val = 0; 
    $range = max(1, (float)$max_val);

    $points = [];
    foreach ($history as $h) {
        $x = (($h['clock'] - $ts_start) / $duration) * $width;
        $y = $height - $padding - (( (float)$h['value'] - $min_val) / $range) * ($height - 2 * $padding);
        $y = max($padding, min($height - $padding, $y)); 
        $points[] = ['x' => round($x, 2), 'y' => round($y, 2)];
    }

    $path_line = "M" . $points[0]['x'] . "," . $points[0]['y'];
    for ($i = 0; $i < count($points) - 1; $i++) {
        $xc = ($points[$i]['x'] + $points[$i+1]['x']) / 2;
        $yc = ($points[$i]['y'] + $points[$i+1]['y']) / 2;
        $path_line .= " Q " . $points[$i]['x'] . "," . $points[$i]['y'] . " " . $xc . "," . $yc;
    }
    $path_line .= " L " . end($points)['x'] . "," . end($points)['y'];
    $path_area = $path_line . " L{$width},{$height} L0,{$height} Z";

    $last_val = (float)$data['items_data'][$itemid]['lastvalue'];
    $color = $get_threshold_color($last_val, $max_val);

    $svg = (new CTag('svg', true))
        ->addClass('item-sparkline')
        ->addClass('sparkline-svg')
        ->setAttribute('viewBox', "0 0 $width $height")
        ->setAttribute('preserveAspectRatio', 'none')
        ->setAttribute('data-clocks', json_encode($clocks))
        ->setAttribute('data-values', json_encode($values))
        ->setAttribute('data-ts-start', $ts_start)
        ->setAttribute('data-ts-end', $ts_end)
        ->setAttribute('data-units', $data['items_data'][$itemid]['units'])
        ->addItem([
            (new CTag('path', true))
                ->setAttribute('d', $path_area)
                ->setAttribute('style', "fill: $color; fill-opacity: 0.1; stroke: none;"),
            (new CTag('path', true))
                ->setAttribute('d', $path_line)
                ->setAttribute('style', "stroke: $color; stroke-width: 1.5; fill: none; stroke-linejoin: round;"),
            (new CTag('line', true))
                ->addClass('sparkline-crosshair')
                ->setAttribute('x1', 0)->setAttribute('y1', 0)
                ->setAttribute('x2', 0)->setAttribute('y2', $height)
                ->setAttribute('style', "stroke: #ffffff; stroke-width: 1; display: none; pointer-events: none;")
        ]);

    return $svg;
};

$container = (new CDiv())->addClass('device-card-container');

if (!empty($data['items_data'])) {
    $items_map = $data['items_data'];
    $mixed_order = [];
    $processed_ids = [];

    if (isset($data['custom_labels']) && is_array($data['custom_labels'])) {
        foreach ($data['custom_labels'] as $config) {
            $id = (string)($config['i'] ?? '');
            if (isset($config['t']) && $config['t'] === 'header') {
                $mixed_order[] = ['type' => 'header', 'name' => $config['n']];
            } elseif ($id !== '' && isset($items_map[$id])) {
                $mixed_order[] = ['type' => 'item', 'id' => $id, 'data' => $items_map[$id], 'config' => $config];
                $processed_ids[] = $id;
            }
        }
    }

    foreach ($items_map as $id => $item) {
        if (!in_array((string)$id, $processed_ids)) {
            $mixed_order[] = ['type' => 'item', 'id' => (string)$id, 'data' => $item, 'config' => []];
        }
    }

    // Prepare groups for Grid and List
    $final_entities = [];
    $i = 0;
    while ($i < count($mixed_order)) {
        $entity = $mixed_order[$i];
        
        if ($entity['type'] === 'header') {
            $final_entities[] = $entity;
            $i++;
            continue;
        }

        // It's an item, handle Join logic
        $item_entry = $entity;
        $group = [
            'type' => 'item_group',
            'name' => (!empty($item_entry['config']['n'])) ? $item_entry['config']['n'] : $item_entry['data']['name'],
            'is_online' => ((float)$item_entry['data']['lastvalue'] > 0),
            'last_value_raw' => (float)$item_entry['data']['lastvalue'],
            'values' => [],
            'graph_id' => (isset($item_entry['config']['g']) && $item_entry['config']['g']) ? $item_entry['id'] : null,
            'max_val' => (float)(!empty($item_entry['config']['m']) ? $item_entry['config']['m'] : 100)
        ];
        
        $conv = $convert_units($item_entry['data']['lastvalue'], $item_entry['data']['units']);
        $group['values'][] = $conv['value'] . $conv['unit'];
        
        while (isset($mixed_order[$i]['config']['j']) && $mixed_order[$i]['config']['j'] == true && isset($mixed_order[$i+1]) && $mixed_order[$i+1]['type'] === 'item') {
            $i++;
            $next_item = $mixed_order[$i];
            $group['max_val'] = (float)$next_item['data']['lastvalue'];
            if ($group['graph_id'] === null && isset($next_item['config']['g']) && $next_item['config']['g']) {
                $group['graph_id'] = $next_item['id'];
            }
            $conv = $convert_units($next_item['data']['lastvalue'], $next_item['data']['units']);
            $group['values'][] = $conv['value'] . $conv['unit'];
        }
        
        $final_entities[] = $group;
        $i++;
    }

    if (!empty($final_entities)) {
        // Find first item group (title) and second (subtitle)
        $first_item = null;
        $subtitle_item = null;
        $subtitle_idx = -1;
        $item_found_count = 0;
        foreach ($final_entities as $idx => $ent) {
            if ($ent['type'] === 'item_group') {
                $item_found_count++;
                if ($item_found_count === 1) {
                    $first_item = $ent;
                } elseif ($item_found_count === 2) {
                    $subtitle_item = $ent;
                    $subtitle_idx = $idx;
                    break;
                }
            }
        }
        
        if ($first_item && !$first_item['is_online']) $container->addClass('is-down');

        $title_row = (new CDiv())->addClass('card-title-row');
        if ((bool)$data['show_status']) {
            $status_class = ($first_item && $first_item['is_online']) ? 'online' : 'offline';
            $title_row->addItem((new CDiv())->addClass('card-dot')->addClass($status_class));
        }
        $main_name = $first_item ? $first_item['name'] : $data['name'];
        $title_row->addItem((new CDiv($main_name))->addClass('card-main-title'));
        if ($subtitle_item !== null) {
            $subtitle_val = implode(' / ', $subtitle_item['values']);
            $title_row->addItem((new CDiv($subtitle_val))->addClass('card-subtitle'));
        }
        if ((bool)$data['show_status']) {
            $status_class = ($first_item && $first_item['is_online']) ? 'online' : 'offline';
            $title_row->addItem((new CDiv($status_class))->addClass('status-badge')->addClass($status_class));
        }
        $container->addItem($title_row);

        $grid_count = (int)$data['grid_count'];
        $grid_items_added = 0;
        
        // Remove entities that go to Grid
        $remaining_entities = [];
        $grid_div = (new CDiv())->addClass('card-grid');
        $has_grid = false;

        $first_item_skipped = false;
        foreach ($final_entities as $idx => $ent) {
            if ($ent['type'] === 'item_group' && !$first_item_skipped) {
                $first_item_skipped = true;
                continue;
            }
            if ($subtitle_item !== null && $idx === $subtitle_idx) {
                continue;
            }

            if ($grid_items_added < $grid_count && $ent['type'] === 'item_group') {
                $block = (new CDiv())->addClass('grid-block');
                $val_text = implode(' / ', $ent['values']);
                
                // ОПРЕДЕЛЯЕМ ЦВЕТ (Только если есть график!)
                $v_style = "";
                if ($ent['graph_id'] !== null) {
                    $v_color = $get_threshold_color($ent['last_value_raw'], $ent['max_val']);
                    $v_style = "color: $v_color;";
                }
                
                $block->addItem((new CDiv($val_text))->addClass('grid-value')->setAttribute('style', $v_style));
                $block->addItem((new CDiv($ent['name']))->addClass('grid-label'));
                
                if ($ent['graph_id']) {
                    $spark = $render_sparkline($ent['graph_id'], $ent['max_val'], 60);
                    if ($spark) {
                        $block->addItem((new CDiv($spark))->addClass('grid-sparkline-container'));
                    }
                }
                $grid_div->addItem($block);
                $grid_items_added++;
                $has_grid = true;
            } else {
                $remaining_entities[] = $ent;
            }
        }
        
        if ($has_grid) $container->addItem($grid_div);

        if (!empty($remaining_entities)) {
            $list_container = (new CDiv())->addClass('card-list-wrapper');
            foreach ($remaining_entities as $ent) {
                if ($ent['type'] === 'header') {
                    $list_container->addItem((new CDiv($ent['name']))->addClass('card-list-section-header'));
                    continue;
                }
                
                $val_text = implode(' / ', $ent['values']);
                $row = (new CDiv())->addClass('card-list-row');
                $row->addItem((new CDiv($ent['name']))->addClass('col-list-label'));
                
                $graph_container = (new CDiv())->addClass('col-list-graph');
                if ($ent['graph_id']) {
                    $spark = $render_sparkline($ent['graph_id'], $ent['max_val'], 18);
                    if ($spark) {
                        $graph_container->addItem((new CDiv($spark))->addClass('item-sparkline-container'));
                    }
                }
                $row->addItem($graph_container);
                
                // ОПРЕДЕЛЯЕМ ЦВЕТ (Только если есть график!)
                $v_style = "";
                if ($ent['graph_id'] !== null) {
                    $v_color = $get_threshold_color($ent['last_value_raw'], $ent['max_val']);
                    $v_style = "color: $v_color;";
                }
                
                $row->addItem((new CDiv($val_text))->addClass('col-list-value')->setAttribute('style', $v_style));
                
                $list_container->addItem($row);
            }
            $container->addItem($list_container);
        }
    }
} else {
    $container->addItem((new CTableInfo())->setNoDataMessage('No items selected'));
}

(new CWidgetView($data))->addItem($container)->show();
