<?php


function getReferenceData() {

	$referenceData = array();

	$sqlProduct = "select * FROM is_product";
	$sqlTenderee = "select * FROM is_tenderee";
	$sqlParticipant = "select * FROM is_participant";
	try {
		$db = getConnection();

		$stmt = $db->query($sqlProduct);  
		$referenceData['products'] = $stmt->fetchAll(PDO::FETCH_OBJ);

		$stmt = $db->query($sqlTenderee);  
		$referenceData['tenderees'] = $stmt->fetchAll(PDO::FETCH_OBJ);

		$stmt = $db->query($sqlParticipant);  
		$referenceData['participants'] = $stmt->fetchAll(PDO::FETCH_OBJ);

		$db = null;
		echo json_encode($referenceData);

	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function getContract($id) {

	$contractFields = array_fill_keys(array(
	  'contract_id', 
	  'tenderee_id', 
	  'tenderee_short_name', 
	  'contract_name', 
	  'contract_registration_no', 
	  'contract_date'
	), null);
	$bidFields = array_fill_keys(array(
	  'bid_id', 
	  'contract_item_id', 
	  'participant_id', 
	  'participant_short_name', 
	  'product_brand', 
	  'bid_price',
	  'bid_value',
	  'is_awarded'
	), null);

	$contractItemFields = array_fill_keys(array(
	  'contract_item_id', 
	  'contract_id', 
	  'product_id', 
	  'product_name', 
	  'quantity' 
	), null);

	$sql= "SELECT
				c.contract_id,
				c.tenderee_id,
				t.tenderee_short_name,
				c.contract_name,
				c.contract_registration_no,
				DATE_FORMAT(c.contract_date,'%d/%m/%Y') AS contract_date,
				i.contract_item_id,
				i.product_id,
				pr.product_name,
				i.quantity,
				b.bid_id,
				b.participant_id,
				pa.participant_short_name,
				b.product_brand,
				b.bid_price,
				b.bid_value,
				b.is_awarded 
			FROM
				is_tenderee t, is_contract c
			LEFT OUTER JOIN is_contract_item i ON i.contract_id=c.contract_id
			LEFT OUTER JOIN is_bid b ON b.contract_item_id=i.contract_item_id
			LEFT OUTER JOIN is_participant pa ON pa.participant_id=b.participant_id
			LEFT OUTER JOIN is_product pr ON pr.product_id=i.product_id
			WHERE 
				c.tenderee_id=t.tenderee_id
			AND     
				c.contract_id=:id";

	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("id", $id);
		$stmt->execute();


		$contract = array();
		$itemList = array();

		foreach ($stmt as $row){
			if (empty($contract)){
				//echo "setting contract \n";
				$contract = array_intersect_key($row, $contractFields);
			}
			if (!is_null($row['contract_item_id'])){
			       $contract_item_id= $row['contract_item_id'];
				if (!isset( $itemList[$contract_item_id])){
					//echo "inserting contract item  $contract_item_id \n";
					$itemList[$contract_item_id] = array_intersect_key($row, $contractItemFields);

					//initially set the winning_bid_id to zero to mean winning bid is not known
					$itemList[$contract_item_id]['winning_bid_id'] = 0;
					//add the empty bids array
					$itemList[$contract_item_id]['bids'] = array();
				} 
			}
			if (!is_null($row['bid_id'])){
				$bid_id = $row['bid_id'];
				$contract_item_id= $row['contract_item_id'];

				$is_awarded = $row['is_awarded'];
				if ($is_awarded == 'Y'){
					//if the winning bid is set corrrect the intial setting
					$itemList[$contract_item_id]['winning_bid_id'] = $bid_id;
				}

				//append the bid to bids array
				$itemList[$contract_item_id]['bids'][] = array_intersect_key($row, $bidFields);
			}
					
		}
		//append items to contract 
		$contract['items'] = array();
		foreach ($itemList as $item_id => $item){
			$contract['items'][] = $item;
		}
		//put contract into an array
		$contracts['contracts'] = array($contract);
		//print_r($contract);
		echo json_encode($contracts);

		$db = null;
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function saveContracts() {
	$request = Slim::getInstance()->request();
	$contracts = json_decode($request->getBody());
	
	//save or update each contract
	foreach ($contracts->contracts as $contract)
	{
		$log = Slim::getInstance()->getLog();
		$log->debug($contract->tenderee_short_name);

		processContract($contract);
	}

	echo json_encode($contracts);
}


function isDeleted($anItem) {
	//if an item  (contract, contract item or bid) is deleted it has _destroy field
	$deleted = false;
	try {

		$hasDestroy = $anItem->_destroy;
		$deleted = true;

	}
	catch (Exception $e) {
		$deleted = false;

	}
	return $deleted;
}

function processContract($contract) {

	if (!isDeleted($contract)||!isNew($contract->contract_id)) {
		//if not deleted or not new contract
		if (isDeleted($contract))
		{
			deleteContract($contract);
		}
		else {
			if (isNew($contract->contract_id))
			{
				addContract($contract);
				$log = Slim::getInstance()->getLog();
				$log->info('added new contract with id:'.$contract->contract_id);
			}
			else
			{
				updateContract($contract);
			}
			//then process contract items
			processContractItems($contract);
		}
	}
	else {
		$log = Slim::getInstance()->getLog();
		$log->debug('deleted and new');
	}
}

function processContractItems($contract) {
	foreach ($contract->items  as $item)
	{
		if (!isDeleted($item)||!isNew($item->contract_item_id)) {
			//if not deleted or not new item
			if (isDeleted($item))
			{
				//delete contract item
				deleteContractItem($item);
			}
			else {
				//update the contract_id in item as it should have been updated with the real 
				//value while adding contract to db
				$item->contract_id = $contract->contract_id;

				if (isNew($item->contract_item_id))
				{
					addContractItem($item);
					$log = Slim::getInstance()->getLog();
					$log->info('added new item with id:'.$item->contract_item_id);
				}
				else
				{
					updateContractItem($item);
				}
				//then process bids
				processBids($item);
			}
		}

	}
}

function deleteContract($contract) {
	$sql = "DELETE FROM is_contract WHERE contract_id=:contract_id";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("contract_id", $contract->contract_id);
		$stmt->execute();
		$db = null;
	} catch(PDOException $e) {
		$log = Slim::getInstance()->getLog();
		$log->error('delete contract with id:'.$contract->contract_id.' ERROR:'.$e->getMessage());
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function addContract($contract) {

	$sql = "INSERT INTO is_contract (tenderee_id, contract_name, contract_registration_no, contract_date) 
                 VALUES (:tenderee_id, :contract_name, :contract_registration_no, :contract_date)";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("tenderee_id", $contract->tenderee_id);
		$stmt->bindParam("contract_name", $contract->contract_name);
		$stmt->bindParam("contract_registration_no", $contract->contract_registration_no);
		$formattedDate = formatDate($contract->contract_date);
		$stmt->bindParam("contract_date", $formattedDate);
		$stmt->execute();
		$contract->contract_id = $db->lastInsertId();
		$db = null;
	} catch(PDOException $e) {
		$log = Slim::getInstance()->getLog();
		$log->error('add contract with id:'.$contract->contract_id.' ERROR:'.$e->getMessage());
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function updateContract($contract) {
	
	$sql = "UPDATE is_contract SET 
				tenderee_id=:tenderee_id, 
				contract_name=:contract_name, 
				contract_registration_no=:contract_registration_no,
				contract_date=:contract_date WHERE contract_id=:contract_id";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("tenderee_id", $contract->tenderee_id);
		$stmt->bindParam("contract_name", $contract->contract_name);
		$stmt->bindParam("contract_registration_no", $contract->contract_registration_no);
		$formattedDate = formatDate($contract->contract_date);
		$stmt->bindParam("contract_date", $formattedDate);
		$stmt->bindParam("contract_id", $contract->contract_id);
		$stmt->execute();
		$db = null;
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function deleteContractItem($item) {
	$sql = "DELETE FROM is_contract_item WHERE contract_item_id=:contract_item_id";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("contract_item_id", $item->contract_item_id);
		$stmt->execute();
		$db = null;
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function addContractItem($item) {

	$sql = "INSERT INTO is_contract_item (contract_id, product_id, quantity) VALUES (:contract_id, :product_id, :quantity)";

	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("contract_id", $item->contract_id);
		$stmt->bindParam("product_id", $item->product_id);
		$stmt->bindParam("quantity", $item->quantity);
		$stmt->execute();
		$item->contract_item_id = $db->lastInsertId();
		$db = null;
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function updateContractItem($item) {
	
	$sql = "UPDATE is_contract_item SET 
				contract_id=:contract_id, 
				product_id=:product_id, 
				quantity=:quantity WHERE contract_item_id=:contract_item_id";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("contract_id", $item->contract_id);
		$stmt->bindParam("product_id", $item->product_id);
		$stmt->bindParam("quantity", $item->quantity);
		$stmt->bindParam("contract_item_id", $item->contract_item_id);
		$stmt->execute();
		$db = null;
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function processBids($item) {
	foreach ($item->bids  as $bid)
	{
		if (!isDeleted($bid)||!isNew($bid->bid_id)) {
			//if not deleted or not new bid
			if (isDeleted($bid))
			{
				//delete bid
				deleteBid($bid);
			}
			else {
				//update the contract_item_id in bid as it should have been updated with
				// new value while inserting to db
				$bid->contract_item_id = $item->contract_item_id;

				//if this is winning bid set it is awarded
				$bid->is_awarded = $bid->is_winning_bid ? 'Y':'N';

				if (isNew($bid->bid_id))
				{
					addBid($bid);

					//update the winning bid id in the item if this is a winning bid
					if ( $bid->is_winning_bid) {
						$item->winning_bid_id = $bid->bid_id; 
					}

					$log = Slim::getInstance()->getLog();
					$log->info('added new bid with id:'.$bid->bid_id.' for contract item:'.$item->contract_item_id);
				}
				else
				{
					updateBid($bid);
				}
			}
		}

	}
}

function deleteBid($bid) {
	$sql = "DELETE FROM is_bid WHERE bid_id=:bid_id";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("bid_id", $bid->bid_id);
		$stmt->execute();
		$db = null;
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function addBid($bid) {

	$sql = "INSERT INTO is_bid (contract_item_id, participant_id, product_brand, bid_price, bid_value, is_awarded)
		VALUES (:contract_item_id, :participant_id, :product_brand, :bid_price, :bid_value, :is_awarded)";

	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("contract_item_id", $bid->contract_item_id);
		$stmt->bindParam("participant_id", $bid->participant_id);
		$stmt->bindParam("product_brand", $bid->product_brand);
		$stmt->bindParam("bid_price", $bid->bid_price);
		$stmt->bindParam("bid_value", $bid->bid_value);
		$stmt->bindParam("is_awarded", $bid->is_awarded);
		$stmt->execute();
		$bid->bid_id = $db->lastInsertId();
		$db = null;
	} catch(PDOException $e) {
		$log = Slim::getInstance()->getLog();
		$log->error('add bid with id:'.$contract->contract_id.' ERROR:'.$e->getMessage());
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function updateBid($bid) {
	
	$sql = "UPDATE is_bid SET 
				contract_item_id=:contract_item_id, 
				participant_id=:participant_id, 
				product_brand=:product_brand, 
				bid_price=:bid_price, 
				bid_value=:bid_value,
				is_awarded=:is_awarded WHERE bid_id=:bid_id";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("contract_item_id", $bid->contract_item_id);
		$stmt->bindParam("participant_id", $bid->participant_id);
		$stmt->bindParam("product_brand", $bid->product_brand);
		$stmt->bindParam("bid_price", $bid->bid_price);
		$stmt->bindParam("bid_value", $bid->bid_value);
		$stmt->bindParam("is_awarded", $bid->is_awarded);
		$stmt->bindParam("bid_id", $bid->bid_id);
		$stmt->execute();
		$db = null;
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function findByContractName($query) {
	$sql = "SELECT * FROM is_contract WHERE UPPER(contract_name) LIKE :query ORDER BY contract_name";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$query = "%".$query."%";  
		$stmt->bindParam("query", $query);
		$stmt->execute();
		$contracts = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		echo  json_encode($contracts);
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}
function getContractNames() {
	$sql = "SELECT * FROM is_contract ORDER BY contract_name";
	try {
                $db = getConnection();
                $stmt = $db->query($sql);
		$contracts = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		echo  json_encode($contracts);
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function formatDate($aDate) {

	//convert dd/mm/yyyy format to mySQL yyyy-dd-mm
        list($d, $m, $y) = preg_split('/\//', $aDate);
        
	return  sprintf('%4d-%02d-%02d', $y, $m, $d);
}

?>
