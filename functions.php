<?php

function strToBin ( $number )    {
  $result = '';
  for ( $i = 0; $i < strlen($number); $i++ ){
      $conv = base_convert($number[$i], 16, 2);
      $result .= str_pad($conv, 4, '0', STR_PAD_LEFT);
  }
  return $result;
}

function array_orderby(){
  $args = func_get_args();
  $data = array_shift($args);
  foreach ($args as $n => $field) {
      if (is_string($field)) {
          $tmp = array();
          foreach ($data as $key => $row){
              $tmp[$key] = $row[$field];            
          }
          $args[$n] = $tmp;
      }
  }
  $args[] = &$data;
  call_user_func_array('array_multisort', $args);
  return array_pop($args);
}

function read_asc_file_as_array($file_path){
  $handle = fopen($file_path, "r");
  if(!$handle){return array();}
  $return_array = array();
  while (($line = fgets($handle)) !== false) {
    $line = utf8_encode($line);
    $return_array[] = explode("#", $line);
  }

  fclose($handle);
  return $return_array;
}

function vectura_hst_infos(){  
  global $mysqli_sfp;
  $hstInfos = array(
    'sfp_to_init' => array(),
    'init_to_sfp' => array(),
    'infos' => array(),
  );
  $result = $mysqli_sfp->query("SELECT id, hstName, init_stop_id FROM `data_nahverkehr_haltestellen_fpj_2022` WHERE 1");
  while($row = $result->fetch_assoc()){ 
    $row['hstName'] = utf8_encode($row['hstName']);
    $hstInfos['infos'][$row['id']] = $row;
    $hstInfos['sfp_to_init'][$row['id']] = $row['init_stop_id'];
    $hstInfos['init_to_sfp'][$row['init_stop_id']] = $row['id'];
  }    
  return $hstInfos;    
}

function vectura_set_trip_sollfahrplan($this_fahrt_data){  
  global $mysqli; 
  $mysqli->query("INSERT INTO `data_nahverkehr_sollfahrplan_v4`
      (`trip_id`, `linie`, `unterlinie`, `richtung`, `version`, `unixtime_daystart`, `abfahrt`, `ankunft`, `abfahrt_sfp_id`, `ankunft_sfp_id`, `fahrt_id_extern`, `fahrt_id_intern`, `haltestellen`) 
    VALUES (
      '".$this_fahrt_data['designated_trip_id']."',
      '".$this_fahrt_data['linie']."',
      '".$this_fahrt_data['unterlinie']."',
      '".$this_fahrt_data['richtung']."',
      '".$this_fahrt_data['version']."',
      '".$this_fahrt_data['unixtime_daystart']."',
      '".$this_fahrt_data['abfahrt']."',
      '".$this_fahrt_data['ankunft']."',
      '".$this_fahrt_data['abfahrt_sfp_id']."',
      '".$this_fahrt_data['ankunft_sfp_id']."',
      '".$this_fahrt_data['fahrt_externe_id']."',
      '".$this_fahrt_data['fahrt_interne_id']."',
      '".json_encode($this_fahrt_data['haltestellenfolge'])."'
    )
  ");
  return true;
}

function vectura_update_haltestellenfolge($this_fahrt_data){  
  global $mysqli; 
  $mysqli->query("UPDATE `data_nahverkehr_sollfahrplan_v4` SET
      `unterlinie` = '".$this_fahrt_data['unterlinie']."',
      `richtung` = '".$this_fahrt_data['richtung']."',
      `haltestellen` = '".json_encode($this_fahrt_data['haltestellenfolge'])."'
    WHERE trip_id = '".$this_fahrt_data['designated_trip_id']."'
  ");
  return true;
}

function getSecondsFromTimeString($timestring){  
  $timestring = str_replace(".", ":", $timestring);
  $timeArray = explode(":",$timestring);
  if(sizeof($timeArray) !== 3){return false;}
  return ($timeArray[0] * 3600 + $timeArray[1] * 60 + $timeArray[2]);
}

function getSecondsFromMinuteString($timestring){  
  $timestring = str_replace(".", ":", $timestring);
  $timeArray = explode(":",$timestring);
  if(sizeof($timeArray) !== 2){return false;}
  return ($timeArray[0] * 60 + $timeArray[1]);
}

function infopool_get_haltestellen(){
  global $infopool_root_dir;
  $file_path = $infopool_root_dir."halteste.asc";
  $data = read_asc_file_as_array($file_path);
  $return_array = array();
  foreach($data as $set){
    $set[0] = trim($set[0]);
    $return_array[$set[0]] = array(
      'hst_id' => $set[0],
      'name_kurz' => trim($set[5]),
      'name_lang' => trim($set[10]),
    );
  }
  return $return_array;
}

function infopool_get_bitfeld(){
  global $infopool_root_dir;
  $file_path = $infopool_root_dir."bitfeld.asc";
  $data = read_asc_file_as_array($file_path);
  $return_array = array();
  foreach($data as $set){
    $set[0] = trim($set[0]);
    $return_array[$set[0]] = substr(strToBin($set[1]), 0, 364);        
  }
  
  return $return_array;
}

function compute_mult($v1, $v2){
  return $v1*$v2;
}

function infopool_get_versionen(){
  global $infopool_root_dir;
  $file_path = $infopool_root_dir."versione.asc";
  $data = read_asc_file_as_array($file_path);  
  $return_array = array();
  foreach($data as $set){
    $set[0] = trim($set[0]);
    $return_array[$set[0]] = array(
      'nummer' => $set[0],
      'name' => $set[1],
      'bitfeld' => trim($set[4]),
      'gueltig_von' => strtotime($set[2]),
      'gueltig_bis' => strtotime($set[3]),
    );
  }  
  return $return_array;
}

function infopool_get_fahrten($linie){
  global $fahrplanjahr;
  global $infopool_root_dir;
  $file_path = $infopool_root_dir."fd".$linie.".asc";
  $data = read_asc_file_as_array($file_path);  
  $return_array = array();
  foreach($data as $set){
    $is_headline = (sizeof($set) == 7) ? true : false;
    if($is_headline){
      if(isset($this_set)){
        $return_array[] = $this_set;
      }
      $version = trim($set[1]);
      $sub_version = trim($set[4]);
      $direction = trim($set[3]);
      $this_set = array(
        'version' => $version,
        'unterlinie' => $sub_version,
        'direction' => $direction,
        'anzahl_fahrten' => trim($set[5]),
        'fahrten' => array(),
      );
    }
    else{      
      $this_set['fahrten'][] = array(
        'abfahrt_hst_order_num' => trim($set[0]),
        'abfahrt_hst_id' => trim($set[1]),
        'abfahrt_uhrzeit' => trim($set[2]),

        'ankunft_hst_order_num' => trim($set[3]),
        'ankunft_hst_id' => trim($set[4]),
        'ankunft_uhrzeit' => trim($set[5]),

        'fzg_typ' => trim($set[6]),
        'fahrtzeitprofil' => trim($set[7]),
        'fahrt_externe_id' => trim($set[8]),
        'fahrt_interne_id' => trim($set[13]),
        
        'bitfeld' => trim($set[12]),
      );
    }    
  }
  $return_array[] = $this_set;
  return $return_array;
}

function infopool_get_linie($linie){
  global $fahrplanjahr;
  global $infopool_root_dir;
  $file_path = $infopool_root_dir."ld".$linie.".asc";
  $data = read_asc_file_as_array($file_path);
  $return_array = array();
  
  foreach($data as $set){        
    $is_headline = (sizeof($set) == 11 OR sizeof($set) == 9) ? true : false;
    if($is_headline){
      if(isset($this_set)){
        $return_array[$this_set['version']][$this_set['sub_version']][$this_set['direction']] = $this_set;
      }
      $version = trim($set[1]);
      if(in_array($fahrplanjahr, array(2019, 2020, 2021))){
        $sub_version = trim($set[4]);
        $direction = trim($set[5]);
      }
      if(in_array($fahrplanjahr, array(2022, 2023))){
        $sub_version = trim($set[3]);
        $direction = trim($set[4]);  
      }
      //$direction = 1;
      $this_set = array(
        'linie' => $linie,
        'version' => $version,
        'sub_version' => $sub_version,
        'direction' => $direction,
        'haltestellen' => array(),
      );      
    }
    else{      
      if(!isset($set[0]) OR trim($set[0]) == ''){        
        continue;
      }
      $this_set['haltestellen'][] = array(
        'hst_id' => trim($set[2]),
        'hst_order_num' => trim($set[0]),
        'name_kurz' => trim($set[1]),
        'time_to_next' => trim($set[6]),
        'distance_to_next' => trim($set[3]),
        'wait_time' => trim($set[7]),
        'show_ankunft_position' => trim($set[4]),
        'show_abfahrt_position' => trim($set[5]),
      );
    }
    
  }
  $return_array[$this_set['version']][$this_set['sub_version']][$this_set['direction']] = $this_set;
  return $return_array;
}