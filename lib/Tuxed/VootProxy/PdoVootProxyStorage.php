<?php

namespace Tuxed\VootProxy;

use \Tuxed\Config as Config;
use \PDO as PDO;

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

    public function getProvider($providerId)
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM ExternalGroupProviders WHERE id = :provider_id");
        $stmt->bindValue(":provider_id", $providerId, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to retrieve provider");
        }

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateProvider($providerId, $data)
    {
        $stmt = $this->_pdo->prepare("UPDATE ExternalGroupProviders SET name = :name, description = :description, endpoint = :endpoint, username = :username, password = :password, filter = :icon WHERE id = :provider_id");
        $stmt->bindValue(":name", $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(":description", $data['description'], PDO::PARAM_STR);
        $stmt->bindValue(":endpoint", $data['endpoint'], PDO::PARAM_STR);
        $stmt->bindValue(":username", $data['username'], PDO::PARAM_STR);
        $stmt->bindValue(":password", $data['password'], PDO::PARAM_STR);
        $stmt->bindValue(":filter", $data['filter'], PDO::PARAM_STR);
        $stmt->bindValue(":provider_id", $providerId, PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to update provider");
        }

        return 1 === $stmt->rowCount();
    }

    public function addProvider($data)
    {
        $stmt = $this->_pdo->prepare("INSERT INTO ExternalGroupProviders (id, name, description, endpoint, username, password, filter) VALUES(:provider_id, :name, :description, :endpoint, :username, :password, :filter)");
        $stmt->bindValue(":provider_id", $data['id'], PDO::PARAM_STR);
        $stmt->bindValue(":name", $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(":description", $data['description'], PDO::PARAM_STR);
        $stmt->bindValue(":endpoint", $data['endpoint'], PDO::PARAM_STR);
        $stmt->bindValue(":username", $data['username'], PDO::PARAM_STR);
        $stmt->bindValue(":password", $data['password'], PDO::PARAM_STR);
        $stmt->bindValue(":filter", $data['filter'], PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to add provider");
        }

        return 1 === $stmt->rowCount();
    }

    public function deleteProvider($providerId)
    {
        $stmt = $this->_pdo->prepare("DELETE FROM ExternalGroupProviders WHERE id = :provider_id");
        $stmt->bindValue(":provider_id", $providerId, PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete provider");
        }

        return 1 === $stmt->rowCount();
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
