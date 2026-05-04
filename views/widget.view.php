<?php declare(strict_types = 0);

$graph_color = '#' . ($data['color_graph'] !== '' ? $data['color_graph'] : '4794eb');
$frame_color = '#' . ($data['color_frame'] !== '' ? $data['color_frame'] : '5a5a5a');
$text_main_color = '#ffffff';
$bg_block_color  = '#' . ($data['color_bg'] !== '' ? $data['color_bg'] : '2f2f2f');
$bin_color_green = '#34af67';
$bin_color_red   = '#e45959';
$bin_color_grey  = 'rgba(171, 168, 168, 0.5)';
$font_size_text = '10px';
$font_size_header = '12px';

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

$grid_container = (new CDiv())
    ->addClass('zabbix-item-grid')
    ->setAttribute('style', '
        display: grid; 
        grid-template-columns: repeat(10, minmax(0, 1fr)); 
        grid-auto-rows: 40px;
        gap: 2px; 
        padding: 10px; 
        align-items: stretch;
    ');

if (!empty($data['items_data'])) {
    $configMap = [];
    $ordered_ids = [];
    if (is_array($data['custom_labels'])) {
        foreach ($data['custom_labels'] as $conf) {
            $id = $conf['i'] ?? $conf['id'] ?? null;
            if ($id !== null) {
                $strId = (string)$id;
                $configMap[$strId] = $conf;
                $ordered_ids[] = $strId;
            }
        }
    }
    foreach (array_keys($data['items_data']) as $id) {
        $strId = (string)$id;
        if (!isset($configMap[$strId])) {
            $ordered_ids[] = $strId;
            $configMap[$strId] = [];
        }
    }
    foreach ($ordered_ids as $itemid) {
        if (!isset($data['items_data'][$itemid])) continue;
        $item = $data['items_data'][$itemid];
        $config = $configMap[(string)$itemid] ?? [];

        $conf_name = $config['n'] ?? $config['name'] ?? '';
        $display_name = ($conf_name !== '') ? $conf_name : $item['name'];

       if (isset($config['g'])) {
            $show_graph = ($config['g'] !== false);
        } elseif (isset($config['graphs'])) {
            $show_graph = ($config['graphs'] !== false);
        } else {
            $show_graph = true;
        }

        $is_binary = !empty($config['b']) || !empty($config['binary']);
        $manual_max = $config['m'] ?? $config['max_value'] ?? '';
        $grid_span = isset($config['w']) ? (int)$config['w'] : (isset($config['width']) ? (int)$config['width'] : 1);
        $grid_span_row = isset($config['h']) ? (int)$config['h'] : (isset($config['height']) ? (int)$config['height'] : 1);

        $final_value = $item['lastvalue'];
        $final_units = $item['units'];
        if (!empty($item['valuemap']) && !empty($item['valuemap']['mappings'])) {
            foreach ($item['valuemap']['mappings'] as $mapping) {
                if ($mapping['value'] == $item['lastvalue']) {
                    $final_value = $mapping['newvalue'];
                    break;
                }
            }
        }
        $converted = $convert_units($final_value, $final_units);
        $final_value = $converted['value'];
        $final_units = $converted['unit'];

        $base_style = "
            position: relative;
            box-sizing: border-box; 
            width: 100%; height: 100%; 
            border-radius: 2px; border: 1px solid {$frame_color}; 
            background: {$bg_block_color}; color: {$text_main_color};
            box-shadow: 0 1px 2px rgba(0,0,0,0.3); 
            overflow: hidden; 
        ";
        if (!$show_graph) {
            $base_style .= "display: flex; flex-direction: column; justify-content: center;";
        }
        
        $grid_style = "grid-column: span {$grid_span}; grid-row: span {$grid_span_row};";
        $item_div = (new CDiv())->setAttribute('style', $base_style . $grid_style);
        
        if ($show_graph) {
            $history = $data['history_data'][$itemid] ?? [];
            $values = array_column($history, 'value');

            if (!empty($values)) {
                $width = 400; $height = 100;
                $count = count($values);
                $step = ($count > 1) ? $width / ($count - 1) : 0;
                $svg_content = [];
                if ($is_binary) {
                    $y_up = 15; $y_down = $height - 2;
                    $path_green = ""; $path_red = ""; $path_grey = ""; $path_fill = "";
                    for ($i = 0; $i < $count - 1; $i++) {
                        $v1 = (float)$values[$i];
                        $x1 = round($i * $step, 2); $x2 = round(($i + 1) * $step, 2);
                        $y1 = ($v1 > 0) ? $y_up : $y_down;
                        $y2 = ((float)$values[$i+1] > 0) ? $y_up : $y_down; 
                        if ($v1 > 0) { $path_green .= "M{$x1},{$y1} L{$x2},{$y1} "; $path_fill .= "M{$x1},{$height} L{$x1},{$y1} L{$x2},{$y1} L{$x2},{$height} Z "; }
                        else { $path_red .= "M{$x1},{$y1} L{$x2},{$y1} "; }
                        if ($y1 !== $y2) $path_grey .= "M{$x2},{$y1} L{$x2},{$y2} ";
                    }
                    if ($path_fill !== "") $svg_content[] = (new CTag('path', true))->setAttribute('d', $path_fill)->setAttribute('style', "fill: rgba(52, 175, 103, 0.15); stroke: none;");
                    if ($path_grey !== "") $svg_content[] = (new CTag('path', true))->setAttribute('d', $path_grey)->setAttribute('style', "stroke: {$bin_color_grey}; stroke-width: 1; fill: none;");
                    if ($path_red !== "") $svg_content[] = (new CTag('path', true))->setAttribute('d', $path_red)->setAttribute('style', "stroke: {$bin_color_red}; stroke-width: 2; fill: none;");
                    if ($path_green !== "") $svg_content[] = (new CTag('path', true))->setAttribute('d', $path_green)->setAttribute('style', "stroke: {$bin_color_green}; stroke-width: 2; fill: none;");
                } else {
                    $min = min($values); 
                    $max = max($values);
                    $unit_clean = trim($final_units); 
                    $scale_min = ($min < 0) ? $min * 1.1 : 0;
                    
                    if (is_numeric($manual_max) && (float)$manual_max > 0) {
                        $user_max = (float)$manual_max;
                        $scale_max = ($max > $user_max) ? $max * 1.1 : $user_max;
                    } else {
                        if ($unit_clean === '%') {
                            $scale_max = 100;
                        } elseif ($unit_clean === '°C' || $unit_clean === 'C') {
                            $threshold = 50;
                            $scale_max = ($max > $threshold) ? $max * 1.1 : $threshold;
                        } else {
                            $scale_max = ($max > 0) ? $max * 1.2 : 10;
                        }
                    }
                    
                    $scale_diff = $scale_max - $scale_min;
                    $start_y = $height - (($values[0] - $scale_min) / $scale_diff) * $height;
                    $path = "M0,{$start_y} ";
                    for ($i = 0; $i < $count - 1; $i++) {
                        $x2 = round(($i + 1) * $step, 2);
                        $y2 = $height - (($values[$i+1] - $scale_min) / $scale_diff) * $height;
                        $path .= "L{$x2},{$y2} ";
                    }
                    $svg_content[] = (new CTag('path', true))
                        ->setAttribute('d', $path . " L{$width},{$height} L0,{$height} Z")
                        ->setAttribute('style', "fill: {$graph_color}; fill-opacity: 0.25; stroke: none;");
                    $svg_content[] = (new CTag('path', true))
                        ->setAttribute('d', $path)
                        ->setAttribute('style', "stroke: {$graph_color}; stroke-width: 1.5; fill: none;");
                    
                    $val_div = (new CDiv())->setAttribute('style', 'position: absolute; top: 0; left: 0; right: 0; bottom: 16px; display: flex; justify-content: center; align-items: center; z-index: 3; ');
                    $val_div->addItem([(new CSpan($final_value))->setAttribute('style', "font-size: {$font_size_header}; font-weight: bold; margin-right: 3px;"), (new CSpan($final_units))->setAttribute('style', "font-size: {$font_size_header}; font-weight: bold; color: {$text_main_color}")]);
                    $item_div->addItem($val_div);
                }
                $svg = (new CTag('svg', true))->setAttribute('width', '100%')->setAttribute('height', '100%')->setAttribute('viewBox', "0 0 $width $height")->setAttribute('preserveAspectRatio', 'none')->addItem($svg_content);
                $item_div->addItem((new CDiv($svg))->setAttribute('style', 'position: absolute; top: 0; left: 0; bottom: 0; right: 0; z-index: 1;'));
                
                $name_div = (new CDiv($display_name))->setAttribute('style', "
                position: absolute; bottom: 2px; left: 0; right: 0; text-align: center; 
                font-size: {$font_size_text}; color: {$text_main_color}; 
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 15px; z-index: 3; ");
                $item_div->addItem($name_div);
            }
        } else {
            $row_div = (new CDiv())->setAttribute('style', 'display: flex; justify-content: center; align-items: center; flex-wrap: wrap; width: 100%; height: 100%; padding: 0 10px; box-sizing: border-box; text-align: center; gap: 6px;');
            $row_div->addItem((new CDiv($display_name))->setAttribute('style', "font-size: {$font_size_text};max-width: 100%;color: {$text_main_color};"));
            $row_div->addItem((new CDiv($final_value . $final_units))->setAttribute('style', "font-size: {$font_size_text};max-width: 100%; color: {$text_main_color};"));
            $item_div->addItem($row_div);
        }
        $grid_container->addItem($item_div);
    }
} else {
    $grid_container = (new CTableInfo())->setNoDataMessage('No data');
}

(new CWidgetView($data))->addItem($grid_container)->show();
