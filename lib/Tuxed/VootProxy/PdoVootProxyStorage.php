<?php

namespace Tuxed\VootProxy;

use \Tuxed\Config as Config;
use \PDO as PDO;

/**
 * Class to implement storage for the OAuth Authorization Server using PDO.
 *
 * FIXME: look into throwing exceptions on error instead of returning FALSE?
 */
class PdoVootProxyStorage
{
    private $_c;
    private $_pdo;

    public function __construct(Config $c)
    {
        $this->_c = $c;

        $driverOptions = array();
        if ($this->_c->getSectionValue('PdoVootProxyStorage', 'persistentConnection')) {
            $driverOptions = array(PDO::ATTR_PERSISTENT => TRUE);
        }

        $this->_pdo = new PDO($this->_c->getSectionValue('PdoVootProxyStorage', 'dsn'), $this->_c->getSectionValue('PdoVootProxyStorage', 'username', FALSE), $this->_c->getSectionValue('PdoVootProxyStorage', 'password', FALSE), $driverOptions);

            $this->_pdo->exec("PRAGMA foreign_keys = ON");
    }

    public function getProviders() {
        $stmt = $this->_pdo->prepare("SELECT * FROM ExternalGroupProviders");
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to retrieve external group providers");
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function initDatabase()
    {
        $this->_pdo->exec("
            CREATE TABLE IF NOT EXISTS `ExternalGroupProviders` (
            `id` VARCHAR(64) NOT NULL,
            `name` TEXT NOT NULL,
            `description` TEXT DEFAULT NULL,
            `endpoint` TEXT NOT NULL,
            `username` TEXT DEFAULT NULL,
            `password` TEXT DEFAULT NULL,
            `filter` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`))
        ");
   }

}
