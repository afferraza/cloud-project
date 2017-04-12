<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
require('../vendor/autoload.php');

$app = new Silex\Application();
$app['debug'] = true;

//Resigtering session
$app->register(new Silex\Provider\SessionServiceProvider());

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__."/views",
));

//redister database
$dbopts = parse_url(getenv('DATABASE_URL'));
$app->register(new Herrera\Pdo\PdoServiceProvider(),
    array(
        'pdo.dsn' => 'pgsql:dbname='.ltrim($dbopts["path"],'/').';host='.$dbopts["host"] . ';port=' . $dbopts["port"],
        'pdo.username' => $dbopts["user"],
        'pdo.password' => $dbopts["pass"]
    )
);

// Our web handlers

$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  if ($app["session"]->get('userId') != null){
    return $app->redirect("/home");
  } else {
      return $app->redirect('/login');
  }
});

$app->get('/login', function(Request $request) use($app) {
  // render login form here
  return $app['twig']->render('login.twig',array(
      'value'=>''
  ));
});

$app->post('/login', function(Request $request) use($app) {
  $name = $request->request->get('name');
  $password = $request->request->get('password');
  // try to log in here
  $query = $app['pdo']->prepare('select * from person where name = \''.$name.'\' and password = \''.$password.'\'');
  $query->execute();
  $res = $query->fetch();
  if($res != false) {
      $app["session"]->set('userId', $res["id"]);
      $app["session"]->set('userType', $res["type"]);
      return $app->redirect('/home');
  } else {
    return $app['twig']->render('login.twig', array(
        'value'=>'User not exists with this name'
    ));
  }
});

$app->get('/home', function(Request $request) use($app) {
    if ($app["session"]->get('userId') == null){
        $app["session"]->clear();
        return $app->redirect('/login');
    }
    $query = $app['pdo']->prepare('select * from item where category = \'1\'');
    $query->execute();
    while ($row = $query->fetch(PDO::FETCH_ASSOC)){
        $cat_1[] = $row;
    }
    $query = $app['pdo']->prepare('select * from item where category = \'2\'');
    $query->execute();
    while ($row = $query->fetch(PDO::FETCH_ASSOC)){
        $cat_2[] = $row;
    }
    return $app['twig']->render('index.twig',array(
        'cat1' => $cat_1,
        'cat2' => $cat_2,
        'type' => $app["session"]->get('userType')
    ));
});

$app->post('/action', function(Request $request) use($app) {
    $id = $_REQUEST["id"];
    if (isset($_REQUEST['edit'])) {
        $query = $app['pdo']->prepare('select * from item where id = \''.$id.'\'');
        $query->execute();
        $query = $query->fetch(PDO::FETCH_ASSOC);
        $cat = $query["category"];
        $name = $query["name"];
        $price = $query["price"];
        $cat==1?$temp=['kar'=>'selected','bur'=>'']:$temp=['kar'=>'','bur'=>'selected'];
        return $app['twig']->render('item.twig',array(
            'id' => $id,
            'name' => $name,
            'category' => $temp,
            'price' => $price,
            'dis' => 'disabled'
        ));
    } else {
        $query = $app['pdo']->prepare('delete from item where id = \''.$id.'\'');
        $query->execute();
        return $app->redirect('/home');
    }
});

$app->get('/addItem', function(Request $request) use($app) {
    if (($app["session"]->get('userId') == null) || ($app["session"]->get('userType') == 2)){
        $app["session"]->clear();
        return $app->redirect('/login');
    }
    $cat = [
        'kar'=>'',
        'bur'=>''
    ];
    return $app['twig']->render('item.twig',array(
        'id' => '',
        'name' => '',
        'category' => $cat,
        'price' => '',
        'dis' => ''
    ));
});

$app->post('/addItem', function(Request $request) use($app) {
    $id = $_REQUEST["id"];
    $name = $_REQUEST["name"];
    $category = $_REQUEST["category"];
    $price = $_REQUEST["price"];
    if (isset($id) && $id!=""){
        $query = $app['pdo']->prepare('update item set price = \''.$price.'\' where id = \''.$id.'\'');
        $query->execute();
    } else {
        $query = $app['pdo']->prepare('select id from item ORDER BY id DESC limit 1');
        $query->execute();
        $id = $query->fetch(PDO::FETCH_ASSOC);
        $id==false?$id=1:$id=$id["id"]+1;
        $query = $app['pdo']->prepare('insert into item values (\''.$id.'\',\''.$category.'\',\''.$name.'\',\''.$price.'\')');
        $query->execute();
    }
    return $app->redirect('/home');
});

$app->get('/offerMenu', function(Request $request) use($app) {
    if ($app["session"]->get('userId') == null){
        $app["session"]->clear();
        return $app->redirect('/login');
    }
    $query = $app['pdo']->prepare('select * from deal');
    $query->execute();
    while ($row = $query->fetch(PDO::FETCH_ASSOC)){
        $deal[] = $row;
    }
    return $app['twig']->render('offer.twig',array(
        'deal' => $deal,
        'type' => $app["session"]->get('userType')
    ));
});

$app->get('/addOffer', function(Request $request) use($app) {
    if (($app["session"]->get('userId') == null) || ($app["session"]->get('userType') == 2)){
        $app["session"]->clear();
        return $app->redirect('/login');
    }
    $items = "";
    $query = $app['pdo']->prepare('select * from item');
    $query->execute();
    while ($row = $query->fetch(PDO::FETCH_ASSOC)){
        $items .= '<option value="'.$row["id"].'">'.$row["name"].'</option>';
    }
    return $app['twig']->render('special.twig',array(
        'id'=>'',
        'items'=>$items,
        'page'=>1,
        'itemCount'=>1
    ));
});

$app->post('/addOffer', function(Request $request) use($app) {
    $id = $_REQUEST["id"];
    $ids = $_REQUEST["name"];
    $quantity = $_REQUEST["qty"];
    $discount = $_REQUEST["discount"];
    if (isset($id) && $id!=""){
        $total = 0;
        $count = 0;
        $result = "";
        foreach ($ids as $i){
            $query = $app['pdo']->prepare('select * from item where id = \''.$i.'\'');
            $query->execute();
            $query = $query->fetch(PDO::FETCH_ASSOC);
            $total = $total + ($query["price"] * $quantity[$count]);
            $result .= $query["id"]."###".trim($query["name"])."###".$quantity[$count]."@@@";
            $count = $count + 1;
        }
        $total = $total - ($total*($discount/100));
        $query = $app['pdo']->prepare('update deal set item = \''.$result.'\', price = \''.$total.'\', discount = \''.$discount.'\' where id = \''.$id.'\'');
        $query->execute();
    } else {
        $total = 0;
        $count = 0;
        $result = "";
        $query = $app['pdo']->prepare('select id from deal ORDER BY id DESC limit 1');
        $query->execute();
        $id = $query->fetch(PDO::FETCH_ASSOC);
        $id==false?$id=1:$id=$id["id"]+1;
        foreach ($ids as $i){
            $query = $app['pdo']->prepare('select * from item where id = \''.$i.'\'');
            $query->execute();
            $query = $query->fetch(PDO::FETCH_ASSOC);
            $total = $total + ($query["price"] * $quantity[$count]);
            $result .= $query["id"]."###".trim($query["name"])."###".$quantity[$count]."@@@";
            $count = $count + 1;
        }
        $total = $total - ($total*($discount/100));
        $query = $app['pdo']->prepare('insert into deal values (\''.$id.'\',\''.$result.'\',\''.$total.'\',\''.$discount.'\')');
        $query->execute();
    }
    return $app->redirect('/offerMenu');
});

$app->post('/actionOffer', function(Request $request) use($app) {
    $id = $_REQUEST["id"];
    if (isset($_REQUEST['edit'])) {
        $query = $app['pdo']->prepare('select * from deal where id = \''.$id.'\'');
        $query->execute();
        $row = $query->fetch(PDO::FETCH_ASSOC);
        $list = $row["item"];
        $temp = explode('@@@',$list);
        $discount = $row["discount"];
        $items = "";
        $query = $app['pdo']->prepare('select * from item');
        $query->execute();
        while ($row = $query->fetch(PDO::FETCH_ASSOC)){
            $items .= '<option value="'.$row["id"].'">'.$row["name"].'</option>';
        }
        return $app['twig']->render('special.twig',array(
            'id'=>$id,
            'items'=>$items,
            'page'=>2,
            'list'=>$list,
            'discount'=>$discount,
            'itemCount'=>(count($temp)-1)
        ));
    } else {
        $query = $app['pdo']->prepare('delete from deal where id = \''.$id.'\'');
        $query->execute();
        return $app->redirect('/offerMenu');
    }
});

$app->get('/logout', function() use($app) {
    $app["session"]->clear();
    return $app->redirect('/login');
});

$app->run();