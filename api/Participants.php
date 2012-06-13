<?php


function findParticipants($query) {
	//if query string is blank return all participants
	$query = trim($query);
	if (empty($query)) {
		return getParticipants();
	}
	else {
		return findByParticipantName($query);
	}
}

function findByParticipantName($query) {
	$sql = "SELECT * FROM is_participant WHERE UPPER(participant_short_name) LIKE :query ORDER BY participant_short_name";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$query = "%".$query."%";  
		$stmt->bindParam("query", $query);
		$stmt->execute();
		$participants = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		echo '{"participants": ' . json_encode($participants) . '}';
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function getParticipants() {
	$sql = "SELECT * FROM is_participant ORDER BY participant_short_name";
	try {
		$db = getConnection();
		$stmt = $db->query($sql);
		$participants = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		echo '{"participants": ' . json_encode($participants) . '}';
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}


function saveParticipants() {
	$request = Slim::getInstance()->request();
	$participants = json_decode($request->getBody());
	
	//save or update each participant
	foreach ($participants->participants as $participant)
	{
		$log = Slim::getInstance()->getLog();
		$log->debug($participant->participant_name);

		processParticipant($participant);
	}

	echo json_encode($participants);
}


function processParticipant($participant) {

	if (isNew($participant->participant_id)) {
		addParticipant($participant);
		$log = Slim::getInstance()->getLog();
		$log->info('added new participant with id:'.$participant->participant_id);
	}
	else {
		updateParticipant($participant);
	}
}

function addParticipant($participant) {

	$sql = "INSERT INTO is_participant (participant_short_name, participant_name, notes) 
                 VALUES (:participant_short_name, :participant_name, :notes)";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("participant_short_name", $participant->participant_short_name);
		$stmt->bindParam("participant_name", $participant->participant_name);
		$stmt->bindParam("notes", $participant->notes);
		$stmt->execute();
		$participant->participant_id = $db->lastInsertId();
		$db = null;
	} catch(PDOException $e) {
		$log = Slim::getInstance()->getLog();
		$log->error('add participant with id:'.$participant->participant_id.' ERROR:'.$e->getMessage());
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function updateParticipant($participant) {
	
	$sql = "UPDATE is_participant SET 
				participant_short_name=:participant_short_name,
				participant_name=:participant_name,
				notes=:notes  WHERE participant_id=:participant_id";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("participant_short_name", $participant->participant_short_name);
		$stmt->bindParam("participant_name", $participant->participant_name);
		$stmt->bindParam("notes", $participant->notes);
		$stmt->bindParam("participant_id", $participant->participant_id);
		$stmt->execute();
		$db = null;
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}
?>
