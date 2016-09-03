<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
$db_users = new SQLite3('user.db');
$db_stats = new SQLite3('/home/myhome/all.sqlite');
$db_log_gpio = new SQLite3('log_gpio.db');
$sens_list = array(
  'id' => array(
    '28_00000448abc3',
    '28_000004e4fd40',
    '28_000005475d6d',
    '28_0415a30f63ff',
    '35_000002793ac7',
    '35_000002793ac8',
    '35_0000034fab32',
    '35_0000034fab33',
    '47_00000451dcf4',
    '81_000000000001',
    '81_000000000002',
    '28_0a020b08010d'
  )
);
$lamp_list = array(
  '1' => 'gpio23',
  '2' => 'gpio24',
  '3' => 'gpio25',
  '4' => 'gpio8'
);
// main
// проверка токена
if(isset($_GET['tooken'])){
  $results =$db_users->query('SELECT * FROM user');
  while($rr = $results->fetchArray(SQLITE3_ASSOC)) {
    if ($_GET['tooken'] == hash('sha256', $_SERVER['REMOTE_ADDR'] + $rr['password'])) {
      $authorized_user = $rr['user'];
    }
  }
  if (!$authorized_user) {
    echo json_encode(array('code' => '403'));
    $motion = 1;
  }
}
// проверка токена
if(isset($_GET['act'])){
  switch ($_GET['act']){
    //авторизация и выдача токена
      case "login":
          if(isset($_GET['user']) and isset($_GET['password'])){
            $results =$db_users->query(sprintf('SELECT * FROM user where user="%s"', $_GET['user'] ));
            while($rr = $results->fetchArray(SQLITE3_ASSOC)) {
              if(count($rr) > 0){
                if($_GET['password'] == $rr['password']){
                  echo json_encode(array('code' => '200', 'user' => $_GET['user'], 'tooken' => hash('sha256', $_SERVER['REMOTE_ADDR'] + $_GET['password'])));
                  $authorized = 1;
                  $motion = 1;
                }
              }
            }
          }
          if (!$authorized) {
            echo json_encode(array('code' => '403'));
            $motion = 1;
          }
      break;
    //авторизация и выдача токена
  }
}
if (isset($_GET['dev']) and in_array($_GET['dev'], $sens_list['id']) and isset($_GET['data']) and $authorized_user){
    switch ($_GET['data']){
      case 'all':
        $results = $db_stats->query('SELECT * FROM data_'.$_GET['dev'].' ORDER BY id;');
            while($rr = $results->fetchArray(SQLITE3_ASSOC)) {
                $data[] = array( ($rr['date']+10800)*1000, $rr['data']);
            }
        echo json_encode(array('code' => '200', 'id' => $_GET['dev'], 'data' => $data));
        $motion = 1;
      break;
      case 'day':
        $results = $db_stats->query('SELECT * FROM (SELECT * FROM data_'.$_GET['dev'].' ORDER BY id DESC LIMIT 288 ) order by id;');
            while($rr = $results->fetchArray(SQLITE3_ASSOC)){
                $data[] = array( ($rr['date']+10800)*1000, $rr['data']);
            }
        $results = $db_stats->query('SELECT * FROM (SELECT * FROM data_'.$_GET['dev'].' ORDER BY id DESC LIMIT 288) ORDER BY data limit 1;');
            while($rr = $results->fetchArray(SQLITE3_ASSOC)){
                $min = $rr['data'];
            }
        $results = $db_stats->query('SELECT * FROM (SELECT * FROM data_'.$_GET['dev'].' ORDER BY id DESC LIMIT 288) ORDER BY data desc limit 1;');
            while($rr = $results->fetchArray(SQLITE3_ASSOC)){
                $max = $rr['data'];
            }
        echo json_encode(array('code' => '200', 'id' => $_GET['dev'], 'min' => $min, 'max' => $max, 'data' => $data));
        $motion = 1;
      break;
      case 'now':
        $results = $db_stats->query('select * from data_'.$_GET['dev'].' where id=(select max(id) from data_'.$_GET['dev'].');');
            while($rr = $results->fetchArray(SQLITE3_ASSOC)) {
                $data = $rr['data'];
            }
        echo json_encode(array('code' => '200', 'id' => $_GET['dev'], 'data' => $data));
        $motion = 1;
      break;
    }
}
if (isset($_GET['lamp']) and isset($_GET['lamp_act']) and $authorized_user) {
  switch ($_GET['lamp_act']){
    case 'on':
      exec('echo 0 > /sys/class/gpio/'.$lamp_list[$_GET['lamp']].'/value');
      echo json_encode(array('code' => '200'));
      $db_log_gpio->query(sprintf('INSERT INTO '.$lamp_list[$_GET['lamp']].'( date, user, ip, action ) VALUES ( "%s", "%s", "%s", "on")',time(),$authorized_user,$_SERVER['REMOTE_ADDR']));
      $motion = 1;
    break;
    case 'off':
      exec('echo 1 > /sys/class/gpio/'.$lamp_list[$_GET['lamp']].'/value');
      $db_log_gpio->query(sprintf('INSERT INTO '.$lamp_list[$_GET['lamp']].'( date, user, ip, action ) VALUES ( "%s", "%s", "%s", "off")',time(),$authorized_user,$_SERVER['REMOTE_ADDR']));
      echo json_encode(array('code' => '200'));
      $motion = 1;
    break;
    case 'status':
      echo json_encode(array('code' => '200', 'data' => array('name' => 'lamp-'.$_GET['lamp'], 'value' => exec('cat  /sys/class/gpio/'.$lamp_list[$_GET['lamp']].'/value'))));
      $motion = 1;
    break;
    case 'log':
      $results = $db_log_gpio->query('SELECT * FROM '.$lamp_list[$_GET['lamp']].' ORDER BY id;');
          while($rr = $results->fetchArray(SQLITE3_ASSOC)) {
              $data[] = array($rr['date'], $rr['user'], $rr['ip'], $rr['action']);
          }
      echo json_encode(array('code' => '200', 'name' => 'lamp-'.$_GET['lamp'], 'log' => $data));
      $motion = 1;
    break;
  }
}
//heating_system
if (isset($_GET['heating_system']) and $authorized_user) {
  switch ($_GET['heating_system']){
    case 'get_mode':
        echo json_encode(array('code' => '200', 'data' => array('name' => 'heating_system_mode', 'value' => exec('cat  /sys/class/gpio/gpio9/value'))));
        $motion = 1;
    break;
    case 'set_mode':
      if(isset($_GET['value']) and preg_match("/^[0-1]$/", $_GET['value'])){
          echo exec("echo ".$_GET['value']." > /home/myhome/mode");
          if($_GET['value'] == "1") $db_log_gpio->query(sprintf('INSERT INTO gpio9 ( date, user, ip, action ) VALUES ( "%s", "%s", "%s", "Mode Auto")',time(),$authorized_user,$_SERVER['REMOTE_ADDR']));
          if($_GET['value'] == "0") $db_log_gpio->query(sprintf('INSERT INTO gpio9 ( date, user, ip, action ) VALUES ( "%s", "%s", "%s", "Mode Hand")',time(),$authorized_user,$_SERVER['REMOTE_ADDR']));
          echo json_encode(array('code' => '200'));
          $motion = 1;
      }
    break;
    case 'get_max_temp':
      echo json_encode(array('code' => '200', 'data' => array('name' => 'heating_system_max_temp', 'value' => exec('cat  /home/myhome/heating_system_max_temp'))));
      $motion = 1;
    break;
    case 'get_min_temp':
      echo json_encode(array('code' => '200', 'data' => array('name' => 'heating_system_min_temp', 'value' => exec('cat  /home/myhome/heating_system_min_temp'))));
      $motion = 1;
    break;
    case 'set_max_temp':
      if(isset($_GET['value']) and preg_match("/^[0-9]{2}$/", $_GET['value'])){
          echo exec("echo ".$_GET['value']." > /home/myhome/heating_system_max_temp");
          echo json_encode(array('code' => '200'));
          $motion = 1;
      }
    break;
    case 'set_min_temp':
      if(isset($_GET['value']) and preg_match("/^[0-9]{2}$/", $_GET['value'])){
          echo exec("echo ".$_GET['value']." > /home/myhome/heating_system_min_temp");
          echo json_encode(array('code' => '200'));
          $motion = 1;
      }
    break;
    case 'pump':
      if (isset($_GET['pump_act'])) {
        switch ($_GET['pump_act']) {
          case 'on':
            exec('echo 1 > /sys/class/gpio/gpio9/value');
            echo json_encode(array('code' => '200'));
            $db_log_gpio->query(sprintf('INSERT INTO gpio9 ( date, user, ip, action ) VALUES ( "%s", "%s", "%s", "on")',time(),$authorized_user,$_SERVER['REMOTE_ADDR']));
            $motion = 1;
          break;
          case 'off':
            exec('echo 0 > /sys/class/gpio/gpio9/value');
            echo json_encode(array('code' => '200'));
            $db_log_gpio->query(sprintf('INSERT INTO gpio9 ( date, user, ip, action ) VALUES ( "%s", "%s", "%s", "off")',time(),$authorized_user,$_SERVER['REMOTE_ADDR']));
            $motion = 1;
          break;
          case 'status':
            echo json_encode(array('code' => '200', 'data' => array('name' => 'pump', 'value' => exec('cat  /sys/class/gpio/gpio9/value'))));
            $motion = 1;
          break;
          case 'log':
            $results = $db_log_gpio->query('SELECT * FROM gpio9 ORDER BY id;');
                while($rr = $results->fetchArray(SQLITE3_ASSOC)) {
                    $data[] = array($rr['date'], $rr['user'], $rr['ip'], $rr['action']);
                }
            echo json_encode(array('code' => '200', 'name' => 'pump', 'log' => $data));
            $motion = 1;
          break;
      }
    }
    break;
  }
}
if (!$motion) {
  echo json_encode(array('code' => '404'));
}
?>
