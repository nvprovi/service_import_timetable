<?php
ini_set('display_errors', '1');
ini_set('max_execution_time', '0');
error_reporting(E_ALL);

require("database.php");
require("functions.php");
require("constants.php");
echo"<pre>";


$bitfeld = infopool_get_bitfeld();
$versionen = infopool_get_versionen();
$haltestellen = infopool_get_haltestellen();
//$sfp_hst_infos = vectura_hst_infos();
$sfp_hst_infos = array();

$select_line = false;
$lines_to_do = array();



foreach($lines_to_do as $linie){
  if($select_line and $select_line != $linie){continue;}
  $linien_info = infopool_get_linie($linie);
  $fahrten_info = infopool_get_fahrten($linie);

  $all_fahrten = array();
  foreach($fahrten_info as $set){
    $this_version_valid_from = $versionen[$set['version']]['gueltig_von'];
    $this_version_bitfeld_info_array = isset($bitfeld[$versionen[$set['version']]['bitfeld']]) ? str_split($bitfeld[$versionen[$set['version']]['bitfeld']]) : array_fill(0, 364, '1');
    if(isset($bitfeld[$versionen[$set['version']]['bitfeld']]) AND array_sum($this_version_bitfeld_info_array) == 0){continue;}
    foreach($set['fahrten'] as $fahrt_info){    
      $this_fahrt_bitfeld_info_array = str_split($bitfeld[$fahrt_info['bitfeld']]);
      if(array_sum($this_fahrt_bitfeld_info_array) == 0){continue;}
      $current_day = $this_version_valid_from;
      $relevant_bitfeld_array = array_map("compute_mult", $this_version_bitfeld_info_array, $this_fahrt_bitfeld_info_array);

      foreach($relevant_bitfeld_array as $day_marker){
        if($day_marker != 1){
          $current_day = strtotime("+1 day", $current_day);
          continue;
        }      
        $this_abfahrt = $current_day + getSecondsFromTimeString($fahrt_info['abfahrt_uhrzeit']);
        $this_fahrt_data = array(
          'designated_trip_id' => $current_day.":".getSecondsFromTimeString($fahrt_info['abfahrt_uhrzeit']).":".$fahrt_info['fahrt_externe_id'].":".$linie,
          'linie' => $linie,
          'version' => $set['version'],
          'unterlinie' => $set['unterlinie'],
          'richtung' => $set['direction'],
          'unixtime_daystart' => $current_day,
          'abfahrt' => $current_day + getSecondsFromTimeString($fahrt_info['abfahrt_uhrzeit']),
          'ankunft' => $current_day + getSecondsFromTimeString($fahrt_info['ankunft_uhrzeit']),
          'abfahrt_text' => date("d.m.Y, H:i:s", $current_day + getSecondsFromTimeString($fahrt_info['abfahrt_uhrzeit'])),
          'ankunft_text' => date("d.m.Y, H:i:s", $current_day + getSecondsFromTimeString($fahrt_info['ankunft_uhrzeit'])),
          'abfahrt_name_kurz' => $haltestellen[$fahrt_info['abfahrt_hst_id']]['name_kurz'],
          'abfahrt_init_id' => substr($fahrt_info['abfahrt_hst_id'], 0, -2),
          'abfahrt_sfp_id' => isset($sfp_hst_infos['init_to_sfp'][substr($fahrt_info['abfahrt_hst_id'], 0, -2)]) ? $sfp_hst_infos['init_to_sfp'][substr($fahrt_info['abfahrt_hst_id'], 0, -2)] : "",
          'abfahrt_steig' => intval(substr($fahrt_info['abfahrt_hst_id'], -2, 2)),
          'ankunft_name_kurz' => $haltestellen[$fahrt_info['ankunft_hst_id']]['name_kurz'],
          'ankunft_init_id' => substr($fahrt_info['ankunft_hst_id'], 0, -2),
          'ankunft_sfp_id' => isset($sfp_hst_infos['init_to_sfp'][substr($fahrt_info['ankunft_hst_id'], 0, -2)]) ? $sfp_hst_infos['init_to_sfp'][substr($fahrt_info['ankunft_hst_id'], 0, -2)] : "",        
          'ankunft_steig' => intval(substr($fahrt_info['ankunft_hst_id'], -2, 2)),
          'fzg_typ' => $fahrt_info['fzg_typ'],
          'fahrtzeitprofil' => $fahrt_info['fahrtzeitprofil'],
          'fahrt_externe_id' => $fahrt_info['fahrt_externe_id'],
          'fahrt_interne_id' => $fahrt_info['fahrt_interne_id'],
          'haltestellenfolge' => array(),
        );
        if(isset($linien_info[$set['version']][$set['unterlinie']][$set['direction']]['haltestellen'])){
          $fahrt_stop_infos = array();
          $current_abfahrt = $this_fahrt_data['abfahrt'];
          foreach($linien_info[$set['version']][$set['unterlinie']][$set['direction']]['haltestellen'] as $stop_info){
            $this_hst_info = array(
              'hst_order_num' => $stop_info['hst_order_num'],
              'init_stop_id' => substr($stop_info['hst_id'], 0, -2),
              'init_stop_id_full' => $stop_info['hst_id'],
              'sfp_stop_id' => isset($sfp_hst_infos['init_to_sfp'][substr($stop_info['hst_id'], 0, -2)]) ? $sfp_hst_infos['init_to_sfp'][substr($stop_info['hst_id'], 0, -2)] : "",
              'name_kurz' => $stop_info['name_kurz'],
              'time_to_next' => getSecondsFromMinuteString($stop_info['time_to_next']),
              'distance_to_next' => $stop_info['distance_to_next'],
              'this_hst_stoptime' => $current_abfahrt,
            );
            $current_abfahrt += $this_hst_info['time_to_next'];
            $fahrt_stop_infos[] = $this_hst_info;
          }
          $this_fahrt_data['haltestellenfolge'] = $fahrt_stop_infos;
        }

        //$all_fahrten[] = implode(", ", $this_fahrt_data);
        $all_fahrten[$this_fahrt_data['designated_trip_id']] = $this_fahrt_data;
        $current_day = strtotime("+1 day", $current_day);
      }    
    }
  }

  $all_fahrten = array_orderby($all_fahrten, 'richtung', SORT_ASC, 'abfahrt', SORT_ASC);  
  foreach($all_fahrten as $trip_id => $this_fahrt_data){
    //vectura_set_trip_sollfahrplan($this_fahrt_data);
    vectura_update_haltestellenfolge($this_fahrt_data);    
    echo $trip_id."\n";
  }    
  echo"<hr>";
}
//print_r($this_fahrt_data);
exit();

// create_data_nahverkehr_haltestellen
if(false){
  foreach($haltestellen as $id => $hst_info){
    $data = create_data_nahverkehr_haltestellen("swr", $hst_info, true);
    print_r($data);
  }

  function create_data_nahverkehr_haltestellen($client_id, $hst_info, $insert = false){
    global $mysqli_sfp; 
    
    $this_hst_infos = array(
      'id' => $hst_info['hst_id'], 
      'client_id' => $client_id, 
      'hstName' => utf8_decode($hst_info['name_lang'])." (".utf8_decode($hst_info['hst_id']).")", 
      'bussteige' => json_encode(array()), 
      'geoInfo' => json_encode(array()), 
      'isochronInfo' => json_encode(array()), 
      'init_code' => utf8_decode($hst_info['name_kurz']),
      'init_stop_id' => $hst_info['hst_id'], 
    );

    if($insert){  
      $mysqli_sfp -> query("INSERT INTO `data_nahverkehr_haltestellen`
          (`id`, `client_id`, `hstName`, `bussteige`, `geoInfo`, `isochronInfo`, `init_code`, `init_stop_id`) 
        VALUES (
          '".$this_hst_infos['id']."',
          '".$this_hst_infos['client_id']."',
          '".utf8_decode($this_hst_infos['hstName'])."',
          '".$this_hst_infos['bussteige']."',
          '".$this_hst_infos['geoInfo']."',
          '".$this_hst_infos['isochronInfo']."',
          '".utf8_decode($this_hst_infos['init_code'])."',
          '".$this_hst_infos['init_stop_id']."'
        )
      ");
    
    }
    return $this_hst_infos;
  }

}


// import data_nahverkehr_lineRoutes
if(false){
  function create_data_nahverkehr_lineRoutes($fpj, $client_id, $linien_info, $insert = false){
    global $mysqli_sfp;
    $hst_array = $linien_info['haltestellen'];
    $fist_stop = array_shift($linien_info['haltestellen']);
    $last_stop = array_pop($linien_info['haltestellen']);
    $return_array = array();
    foreach($hst_array as $index => $hst_infos){
      $this_hst_infos = array(
        'fahrplanjahr' => $fpj, 
        'client_id' => $client_id, 
        'line' => $linien_info['linie'], 
        'direction' => $last_stop['hst_id'], 
        'startHS' => $fist_stop['hst_id'], 
        'stopnumber' => $hst_infos['hst_order_num'], 
        'hst' => $hst_infos['hst_id'], 
        'steig' => intval(substr($hst_infos['hst_id'], -2, 2))
      );
      $return_array[$index] = $this_hst_infos;
    }
    if($insert){
      foreach($return_array as $index => $hst_infos){
        $mysqli_sfp -> query("INSERT INTO `data_nahverkehr_lineRoutes` (`fahrplanjahr`, `client_id`, `line`, `direction`, `startHS`, `stopnumber`, `hst`, `steig`) VALUES (
            '".$hst_infos['fahrplanjahr']."',
            '".$hst_infos['client_id']."',
            '".$hst_infos['line']."',
            '".$hst_infos['direction']."',
            '".$hst_infos['startHS']."',
            '".$hst_infos['stopnumber']."',
            '".$hst_infos['hst']."',
            '".$hst_infos['steig']."'
          )
        ");
      }
    }
    return $return_array;
  }
  
  
  foreach($lines_to_do as $linie){
    if($select_line and $select_line != $linie){continue;}
    $linien_info = infopool_get_linie($linie);
    $select_version = 1;
    $select_subversion = 1;
    $select_direction = 1;
    $current_size = 0;
    foreach($linien_info as $version => $subversion_array){
      foreach($subversion_array as $subversion_id => $direction_array){
        foreach($direction_array as $direction_id => $this_line_infos){
          if($current_size == 0 OR sizeof($this_line_infos['haltestellen']) > sizeof($linien_info[$select_version][$select_subversion][$select_direction]['haltestellen'])){
            $select_version = $version;
            $select_subversion = $subversion_id;
            $select_direction = $direction_id;
          }
        }
      }
    }
    $create_array = create_data_nahverkehr_lineRoutes(2023, "swr", $linien_info[$select_version][$select_subversion][$select_direction], true);
    echo "LINIE ".$linie."<br>";
    print_r($create_array);
    echo"<hr>";
  }
}

