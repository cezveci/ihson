<?php


function findTenderees($query) {
	//if query string is blank return all tenderees
	$query = trim($query);
	if (empty($query)) {
		return getTenderees();
	}
	else {
		return findByTendereeName($query);
	}
}

function findByTendereeName($query) {
	$sql = "SELECT * FROM is_tenderee WHERE UPPER(tenderee_short_name) LIKE :query ORDER BY tenderee_short_name";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$query = "%".$query."%";  
		$stmt->bindParam("query", $query);
		$stmt->execute();
		$tenderees = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		echo '{"tenderees": ' . json_encode($tenderees) . '}';
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function getTenderees() {
	$sql = "SELECT * FROM is_tenderee ORDER BY tenderee_short_name";
	try {
		$db = getConnection();
		$stmt = $db->query($sql);
		$tenderees = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		echo '{"tenderees": ' . json_encode($tenderees) . '}';
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}


function saveTenderees() {
	$request = Slim::getInstance()->request();
	$tenderees = json_decode($request->getBody());
	
	//save or update each tenderee
	foreach ($tenderees->tenderees as $tenderee)
	{
		$log = Slim::getInstance()->getLog();
		$log->debug($tenderee->tenderee_name);

		processTenderee($tenderee);
	}

	echo json_encode($tenderees);
}


function processTenderee($tenderee) {

	if (isNew($tenderee->tenderee_id)) {
		addTenderee($tenderee);
		$log = Slim::getInstance()->getLog();
		$log->info('added new tenderee with id:'.$tenderee->tenderee_id);
	}
	else {
		updateTenderee($tenderee);
	}
}

function addTenderee($tenderee) {

	$sql = "INSERT INTO is_tenderee (tenderee_short_name, tenderee_name) 
                 VALUES (:tenderee_short_name, :tenderee_name)";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("tenderee_short_name", $tenderee->tenderee_short_name);
		$stmt->bindParam("tenderee_name", $tenderee->tenderee_name);
		$stmt->execute();
		$tenderee->tenderee_id = $db->lastInsertId();
		$db = null;
	} catch(PDOException $e) {
		$log = Slim::getInstance()->getLog();
		$log->error('add tenderee with id:'.$tenderee->tenderee_id.' ERROR:'.$e->getMessage());
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function updateTenderee($tenderee) {
	
	$sql = "UPDATE is_tenderee SET 
				tenderee_short_name=:tenderee_short_name,
				tenderee_name=:tenderee_name WHERE tenderee_id=:tenderee_id";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("tenderee_short_name", $tenderee->tenderee_short_name);
		$stmt->bindParam("tenderee_name", $tenderee->tenderee_name);
		$stmt->bindParam("tenderee_id", $tenderee->tenderee_id);
		$stmt->execute();
		$db = null;
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}
?>
