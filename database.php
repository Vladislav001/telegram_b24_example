<?

class Database
{
	protected static $instance;

	/**
	 * @var \mysqli|null
	 */
	protected $connection = null;

	protected function __construct()
	{
		$config = static::getSettings();
		$this->connection = mysqli_connect($config['DB_HOST'], $config['DB_LOGIN'], $config['DB_PASSWORD'], $config['DB_NAME']);

		if ($this->connection->connect_errno)
		{
			throw new \Exception('Database connect error:' . $this->connection->connect_error);
		}
	}

	protected static function getSettings()
	{
		$settings = parse_ini_file($_SERVER['DOCUMENT_ROOT'] . '/settings.ini');
		return array(
			"DB_HOST" => $settings['DB_HOST'],
			"DB_LOGIN" => $settings['DB_LOGIN'],
			"DB_PASSWORD" => $settings['DB_PASSWORD'],
			"DB_NAME" => $settings['DB_NAME'],
			"DB_TABLE_NAME" => $settings['DB_TABLE_NAME']
		);
	}

	protected static function getInstallTableName() {
	    $settings = self::getSettings();
	    $result = $settings['DB_TABLE_NAME'];
	    return $result;
    }

	/**
	 * @return static
	 */
	public static function getInstance()
	{
		if (!static::$instance)
		{
			static::$instance = new Database();
		}

		return static::$instance;
	}

	protected function checkDatabase()
	{
	    $tableName = self::getInstallTableName();
		$sql = "CREATE TABLE IF NOT EXISTS $tableName (
			ID INT(11) NOT NULL AUTO_INCREMENT,
			URL_BITRIX24 VARCHAR(2000),
			DATE_INSTALL TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			MEMBER_ID VARCHAR(2000),
			AUTH_ID VARCHAR(2000),
			REFRESH_ID VARCHAR(2000),
			DATE_UPDATE_REFRESH_ID TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (ID)
		);";
		$result = $this->connection->query($sql);

		if (!$result)
		{
			throw new \Exception('Database query error: ' . $this->connection->error);
		}
	}

	/**
	 * The method adds information about cloud B24
	 * @param array $data data of $_REQUEST
	 */
	public function addB24($data)
	{
		$this->checkDatabase();
		$tableName = self::getInstallTableName();
		$stmt = $this->connection->prepare("INSERT INTO $tableName (URL_BITRIX24, MEMBER_ID, AUTH_ID, REFRESH_ID) VALUES(?, ?, ?, ?)");
		$stmt->bind_param('ssss', $data['DOMAIN'], $data['member_id'], $data['AUTH_ID'], $data['REFRESH_ID']);
		$stmt->execute();
	}

	/**
	 * The method returns the record ID with the previously installed application for the domain
	 * @param string $domain
	 * @return int
	 */
	public function getDomainID($domain)
	{
	    $tableName = self::getInstallTableName();
		$this->checkDatabase();
		$sql = "SELECT * FROM $tableName WHERE URL_BITRIX24=? LIMIT 1";
		$stmt = $this->connection->prepare($sql);
		$stmt->bind_param('s', $domain);
		$stmt->execute();
		$result = $stmt->get_result()->fetch_assoc();

		return $result;
	}

	/**
	 * The method returns module information by domain and member_id
	 * @param string $domain
	 * @param string $memberID
	 * @return array
	 */
	public function getModuleInformationByDomain($domain, $memberID)
	{
		$this->checkDatabase();
		$tableName = self::getInstallTableName();
		$sql = "SELECT * FROM $tableName WHERE URL_BITRIX24=? AND MEMBER_ID=?";
		$stmt = $this->connection->prepare($sql);
		$stmt->bind_param('ss', $domain, $memberID);
		$stmt->execute();
		$result = $stmt->get_result();
		return $result->fetch_assoc();
	}

	/**
	 * The method updates AUTH_ID
	 * @param int $id
	 * @param string $authID
	 */
	public function updateAuthID($id, $authID)
	{
        $tableName = self::getInstallTableName();
        $sql = "UPDATE $tableName SET AUTH_ID=? WHERE ID=?";
		$stmt = $this->connection->prepare($sql);
		//$error = $this->connection->errno . ' ' . $this->connection->error;
		$stmt->bind_param('si', $authID, $id);
		$stmt->execute();
	}

	/**
	 * The method updates REFRESH_ID
	 * @param int $id
	 * @param string $authID
	 */
	public function updateRefreshID($id, $refreshID)
	{
        $tableName = self::getInstallTableName();
        $sql = "UPDATE $tableName SET REFRESH_ID=? WHERE ID=?";
		$stmt = $this->connection->prepare($sql);
		$stmt->bind_param('si', $refreshID, $id);
		$stmt->execute();
	}

	/**
	 * The method updates the last-modified date REFRESH_ID
	 * @param int $id
	 * @param string $date
	 */
	public function updateDateRefreshID($id, $date)
	{
		$tableName = self::getInstallTableName();
		$sql = "UPDATE $tableName SET DATE_UPDATE_REFRESH_ID=? WHERE ID=?";
		$stmt = $this->connection->prepare($sql);
		$stmt->bind_param('si', $date, $id);
		$stmt->execute();
	}

	/**
	 * The method returns information about all modules
	 * @return array
	 */
	public function getAllModules()
	{
		$arModules = array();

		$this->checkDatabase();
        $tableName = self::getInstallTableName();
        $sql = "SELECT * FROM $tableName";
		$stmt = $this->connection->prepare($sql);
		$stmt->execute();
		$result = $stmt->get_result();

		while ($module = $result->fetch_assoc())
		{
			$arModules[] = $module;
		}

		return $arModules;
	}

}