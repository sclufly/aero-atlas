<?php
  
    class dbPDO extends PDO {
        private $dbError;
        private $isConnected;
        private $adminName;
        private $adminPassword;
        private $dbName;
        private $host;
        private $conn;
        private $engine;
        private $port;
    
        // constructor
        public function __construct() {
            $this->engine = "";
            $this->host = "";
            $this->dbName = "";
            $this->port = "";
            $this->adminName = "";
            $this->adminPassword = "";
            $dsn = $this->engine.":dbname=".$this->dbName.";host=".$this->host.";port=".$this->port;
            parent::__construct( $dsn, $this->adminName, $this->adminPassword );
            
            // set current date/time
            $now = new DateTime();
            $mins = $now->getOffset() / 60;
            $sgn = ($mins < 0 ? -1 : 1);
            $mins = abs($mins);
            $hrs = floor($mins / 60);
            $mins -= $hrs * 60;
            $offset = sprintf('%+d:%02d', $hrs*$sgn, $mins);
            $query = "set time_zone='$offset'";
        
            $query = $this->prepare( $query );
        
            if ( !$query->execute() ){
                throw new DBException( "ERROR: Failed to set timezone. Query: ".$query );              
            }  
        
            $query->closeCursor();
        }

        // general query: select all
        private function selectAll( $table, $col, $val ){

            // create query
            $query = $this->prepare( "SELECT * FROM $table WHERE $col = :v" );

            // bind parameters
            if ( !($query->bindParam( ':v', $val ) ) ){
                throw new Exception( "Failed to bind parameter in selectAll. Query: ".$query );      
            }
            
            // execute query
            if ( !$query->execute() ){
                throw new Exception( "Failed to execute query in selectAll. Query: ".$query );              
            }
            
            # return results
            $rowArray = $query->fetchAll();
            return $rowArray;
        }

        // general query: insert into
        private function insertInto( $table, $data ){

            echo "in insertInto";

            // create query
            $cols = implode(', ', array_keys($data));
            $vals = ':' . implode(', :', array_keys($data));
            $query = $this->prepare( "INSERT INTO $table ($cols) VALUES ($vals)" );

            echo "query created";

            // bind parameters
            $boundParams = array();
            foreach ($data as $column => $value) {
                if (!$query->bindParam(":$column", $value)) {
                    throw new Exception("Failed to bind parameter in insertInto. Parameter: :$column");
                }
                $boundParams[":$column"] = $value;
            }

            echo "param binding done \n";
            echo $query."\n";

            // execute query
            if ( !$query->execute($boundParams) ){
                $arr = $query->errorInfo();
                throw new Exception( "Failed to execute query in insertInto. Query: ".$query ); 
            }

            echo "execution over";

            $query->closeCursor();
        }

        // insert data into aero_airplane_type
        private function insertAeroAirplaneType( $plane_type ){

            // get current rows with this plane_type
            $rowArray = $this->selectAll( "aero_airplane_type", "plane_type", $plane_type );

            // if current plane_id is not already in the table
            if ( sizeof ( $rowArray ) == 0 ){

                // add a new row to aero_airplane_type
                $query = "INSERT INTO aero_airplane_type (plane_type) VALUES (:p1)";

                $query = $this->prepare( $query );

                // bind parameters
                if ( !($query->bindParam( ':p1', $plane_type ) )){
                    throw new Exception( "Failed to bind parameters in insertAeroAirplaneType. Query: ".$query );
                }

                // execute query
                if ( !$query->execute() ){
                    $arr = $query->errorInfo();
                    throw new Exception( "Failed to execute query in insertAeroAirplaneType. Query: ".$query ); 
                }

                $query->closeCursor();
            }
        }

        // insert data into aero_airplane
        private function insertAeroAirplane( $plane_id, $plane_type ){

            // get current rows with this plane_id
            $rowArray = $this->selectAll( "aero_airplane", "plane_id", $plane_id );

            // if current plane_id is not already in the table
            if ( sizeof( $rowArray ) == 0 ){

                // add a new row to aero_airplane
                // $data = array(
                //     "plane_id" => $plane_id,
                //     "plane_type" => $plane_type
                // );
                // $this->insertInto( "aero_airplane", $data);

                $query = "INSERT INTO aero_airplane (plane_id, plane_type) VALUES (:p1, :p2)";

                $query = $this->prepare( $query );

                // bind parameters
                if ( !($query->bindParam( ':p1', $plane_id ) ) || !($query->bindParam( ':p2', $plane_type ) )){
                    throw new Exception( "Failed to bind parameters in insertAeroAirplane. Query: ".$query );
                }

                // execute query
                if ( !$query->execute() ){
                    $arr = $query->errorInfo();
                    throw new Exception( "Failed to execute query in insertAeroAirplane. Query: ".$query ); 
                }

                $query->closeCursor();

                // insert into aero_airplane_type
                $this->insertAeroAirplaneType( $plane_type );
            }
        }

        // insert data into aero_airport
        private function insertAeroAirport( $code, $city, $country ){

        }

        // insert data into aero_trip
        private function insertAeroTrip ( $plane_id, $flight_num, $ori_code, $ori_city, $ori_country, $des_code, $des_city, $des_country ){

        } 

        // insert data into aero_trip_data
        private function insertAeroTripData( $lat, $lon, $alt, $speed, $roll, $heading, $squawk, $nav_modes ){

        }
    
        // create entry function
        public function createEntry( $data ){

            // 1. insert data into aero_airplane & aero_airplane_type
            $this->insertAeroAirplane( $data["plane_id"], $data["plane_type"] );

            /*
            // 2. insert data into aero_trip & aero_airport
            $this->insertAeroTrip( $data["plane_id"], $data["flight_num"], 
                                   $data["ori_code"], $data["ori_city"], $data["ori_country"], 
                                   $data["des_code"], $data["des_city"], $data["des_country"] );

            // 3. insert data into aero_trip_data
            $this->insertAeroTripData( $data["lat"], $data["lon"], $data["alt"], 
                                       $data["speed"], $data["roll"], $data["heading"], 
                                       $data["squawk"], $data["nav_modes"] );
            */
        }
    
    }
    
    $error = "OK";
    $data_names = array(
        "flight_num", "plane_id", "lat", "lon", "alt", "speed", 
        "roll", "heading", "squawk", "nav_modes", "plane_type", 
        "ori_code", "ori_city", "ori_country", "des_code", 
        "des_city", "des_country"
    );
    
    // get plane data from request params & store them in $data
    $data = array();
    foreach ($data_names as $param) {
        $data[$param] = htmlspecialchars($_GET[$param]);
    }
    
    // set up database object
    try {
        $db = new dbPDO();
    } catch ( Exception $e ) {
        $error = "ERROR: ".$e->getMessage()."\n";
    }
    
    // create entry for the plane data
    if ( $error == "OK" ) {
        try {
            $db->createEntry( $data );
        } catch ( Exception $e ){
            $error = "ERROR: ".$e->getMessage()."\n";
        }
    }
        
    echo $error;
?>