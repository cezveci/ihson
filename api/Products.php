<?php


function findProducts($query) {
	//if query string is blank return all products
	$query = trim($query);
	if (empty($query)) {
		return getProducts();
	}
	else {
		return findByProductName($query);
	}
}

function findByProductName($query) {
	$sql = "SELECT * FROM is_product WHERE UPPER(product_name) LIKE :query ORDER BY product_name";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$query = "%".$query."%";  
		$stmt->bindParam("query", $query);
		$stmt->execute();
		$products = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		echo '{"products": ' . json_encode($products) . '}';
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function getProducts() {
	$sql = "SELECT * FROM is_product ORDER BY product_name";
	try {
		$db = getConnection();
		$stmt = $db->query($sql);
		$products = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		echo '{"products": ' . json_encode($products) . '}';
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}


function saveProducts() {
	$request = Slim::getInstance()->request();
	$products = json_decode($request->getBody());
	
	//save or update each product
	foreach ($products->products as $product)
	{
		$log = Slim::getInstance()->getLog();
		$log->debug($product->product_name);

		processProduct($product);
	}

	echo json_encode($products);
}


function processProduct($product) {

	if (isNew($product->product_id)) {
		addProduct($product);
		$log = Slim::getInstance()->getLog();
		$log->info('added new product with id:'.$product->product_id);
	}
	else {
		updateProduct($product);
	}
}

function addProduct($product) {

	$sql = "INSERT INTO is_product (product_category_id, product_name) 
                 VALUES (:product_category_id, :product_name)";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("product_category_id", $product->product_category_id);
		$stmt->bindParam("product_name", $product->product_name);
		$stmt->execute();
		$product->product_id = $db->lastInsertId();
		$db = null;
	} catch(PDOException $e) {
		$log = Slim::getInstance()->getLog();
		$log->error('add product with id:'.$product->product_id.' ERROR:'.$e->getMessage());
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function updateProduct($product) {
	
	$sql = "UPDATE is_product SET 
				product_category_id=:product_category_id,
				product_name=:product_name WHERE product_id=:product_id";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("product_category_id", $product->product_category_id);
		$stmt->bindParam("product_name", $product->product_name);
		$stmt->bindParam("product_id", $product->product_id);
		$stmt->execute();
		$db = null;
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}
?>
