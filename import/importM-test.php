<?php
$servername = "localhost";
$usernameO = "objednavky_test2_db";
$passwordO = "D0br0mysl";
$dbnameO = "objednavky-test2";

$usernameM = "money";     // ma jenom SELECT
$passwordM = "FungujKrame!";
$dbnameM = "money_migrace";


// Create connection Migrace
$connM = new mysqli($servername, $usernameM, $passwordM, $dbnameM);
// Check connection Migrace
if ($connM->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}




$sql = "SELECT * from denik_import";
$result = $connM->query($sql);

if ($result->num_rows > 0) {
    // output data of each row
    while($row = $result->fetch_assoc()) {
       
//        $rok = date("Y",strtotime($row["Datum"]));
        $rok = $row["Datum"]->format('Y');
       
     
        
        echo $row["CisloDokladu"]." " .  $row["Popis"]. " " . $row["MD"] . " " . $row["Dal"] . " " . $row["ParovaciSymbol"] ."\n";
    }
} else {
    echo "0 results";
}

$connM->close();   // connection Migrace

/*


// Create connection Objednávky
$connO = new mysqli($servername, $usernameO, $passwordO, $dbnameO);
// Check connection Objednávky
if ($connO->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$sql = "SELECT rok, verze from setup where id=1";

$result = $connO->query($sql);


if ($result->num_rows > 0) {
    // output data of each row
    while($row = $result->fetch_assoc()) {
        $verze = $row["verze"];
        $rok = $row["rok"];
    }
} else {
    echo "Chybí setup!";
}

  // smaž letošní data
$sql ="DELETE denik FROM `denik` inner join rozpocet on denik.rozpocet=rozpocet.id where rozpocet.rok" . $rok;
$result = $connO->query($sql);





$connO->close();   // connection Objednávky
*/
?>
