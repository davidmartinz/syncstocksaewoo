<?php
require __DIR__ . '/vendor/autoload.php';
use Automattic\WooCommerce\Client;

// Conexión WooCommerce API destino
// ================================
$url_API_woo = 'my_url';
$ck_API_woo = 'ck';
$cs_API_woo = 'cs';

$woocommerce = new Client(
    $url_API_woo,
    $ck_API_woo,
    $cs_API_woo,
    [
        'wp-_api' => true,
        'version' => 'wc/v3', 
        'verify_ssl' => false,
        'timeout' => 800, // curl timeout
        'query_string_auth' => true,
        'verify_ssl' => false
    ]
);
// ================================


// Conexión API origen
// ===================
$endpoint = "my-endpoint";
//$params = array(
//    'search_fields' => 'ctrl_alm',
//    'search' => 'WEB');
$url_API = $endpoint; 
//. '?' . http_build_query($params);
$headers = array(
    'Content-Type:application/json',
    'Authorization: Token my-token-in-numbers',);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_API);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// Comienza proceso
echo "➜ Obteniendo datos origen ... \n";
$items_origin = curl_exec($ch);
curl_close($ch);

if ( ! $items_origin ) {
    exit('❗Error en API origen');
}
// ===================


// Obtenemos datos de la API de origen
$items_origin = json_decode($items_origin, true);

// formamos el parámetro de lista de SKUs a actualizar
$param_sku ='';
foreach ($items_origin as $item){
    $param_sku .= $item['cve_art'] . ',';
}

echo "➜ Obteniendo los ids de los productos... \n";
// Obtenemos todos los productos de la lista de SKUs

//Originalmente:
//$products = $woocommerce->get('products/?sku='. $param_sku);

// Loop en todas las paginas de wc v3
$page = 1;
$products = [];
$all_products = [];
do{
  try {
    $products = $woocommerce->get('products/?sku='. $param_sku, array('per_page' => 50, 'page' => $page));
  }catch(HttpClientException $e){
    die("Can't get products: $e");
  }
  $all_products = array_merge($all_products,$products);
  $page++;
} while (count($products) > 0);


// Construimos la data en base a los productos recuperados
// cambios hechos apra aceptar all_products en lugar de solo product
$item_data = [];
foreach($all_products as $all_product){
//foreach($products as $product){

    // Filtramos el array de origen por sku
    //$sku = $product->sku;
    $sku = $all_product->sku;
    $search_item = array_filter($items_origin, function($item) use($sku) {
        return $item['cve_art'] == $sku;});
    $search_item = reset($search_item);

    // Formamos el array a actualizar
    $item_data[] = [
        'id' => $all_product->id,
      //'regular_price' => $search_item['precio'],
        'stock_quantity' => $search_item['exist']
    ];

}

// Construimos información a actualizar en lotes
$data = [
    'update' => $item_data,
];

echo "➜ Actualización en lote ... \n";
// Actualización en lotes
$result = $woocommerce->post('products/batch', $data);

if (! $result) {
    echo("❗Error al actualizar productos \n");
} else {
    print("✔ Productos actualizados correctamente \n");
}