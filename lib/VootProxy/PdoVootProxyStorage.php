<?php

namespace VootProxy;

use \RestService\Utils\Config as Config;
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

    public function getProviders()
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM providers");
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to retrieve entries");
        }

        return $this->_cleanupDBResult($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getProvider($providerId)
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM providers WHERE id = :id");
        $stmt->bindValue(":id", $providerId, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to retrieve entry");
        }
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        return (FALSE === $r) ? FALSE : json_decode($r['data'], TRUE);
    }

    public function updateProvider($providerId, $data)
    {
        $stmt = $this->_pdo->prepare("UPDATE providers SET data = :data WHERE id = :id");
        $stmt->bindValue(":id", $providerId, PDO::PARAM_STR);
        $stmt->bindValue(":data", json_encode($data), PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to update entry");
        }

        return 1 === $stmt->rowCount();
    }

    public function addProvider($data)
    {
        $stmt = $this->_pdo->prepare("INSERT INTO providers (id, data) VALUES(:id, :data)");
        $stmt->bindValue(":id", $data['id'], PDO::PARAM_STR);
        $stmt->bindValue(":data", json_encode($data), PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to add entry");
        }

        return 1 === $stmt->rowCount();
    }

    public function deleteProvider($providerId)
    {
        $stmt = $this->_pdo->prepare("DELETE FROM providers WHERE id = :id");
        $stmt->bindValue(":id", $providerId, PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete entry");
        }

        return 1 === $stmt->rowCount();
    }

    public function getClients()
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM clients");
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to retrieve entries");
        }

        return $this->_cleanupDBResult($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getClient($clientId)
    {
        $stmt = $this->_pdo->prepare("SELECT * FROM clients WHERE id = :id");
        $stmt->bindValue(":id", $clientId, PDO::PARAM_STR);
        $result = $stmt->execute();
        if (FALSE === $result) {
            throw new StorageException("unable to retrieve entry");
        }
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        return (FALSE === $r) ? FALSE : json_decode($r['data'], TRUE);
    }

    public function updateClient($clientId, $data)
    {
        $stmt = $this->_pdo->prepare("UPDATE clients SET data = :data WHERE id = :id");
        $stmt->bindValue(":id", $clientId, PDO::PARAM_STR);
        $stmt->bindValue(":data", json_encode($data), PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to update entry");
        }

        return 1 === $stmt->rowCount();
    }

    public function addClient($data)
    {
        $stmt = $this->_pdo->prepare("INSERT INTO clients (id, data) VALUES(:id, :data)");
        $stmt->bindValue(":id", $data['id'], PDO::PARAM_STR);
        $stmt->bindValue(":data", json_encode($data), PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to add entry");
        }

        return 1 === $stmt->rowCount();
    }

    public function deleteClient($clientId)
    {
        $stmt = $this->_pdo->prepare("DELETE FROM clients WHERE id = :id");
        $stmt->bindValue(":id", $clientId, PDO::PARAM_STR);
        if (FALSE === $stmt->execute()) {
            throw new StorageException("unable to delete entry");
        }

        return 1 === $stmt->rowCount();
    }

    /**
     * This function extracts just the data from all the results and turns
     * it into an array.
     */
    private function _cleanupDBResult($data)
    {
        $processedData = array();
        foreach ($data as $v) {
            array_push($processedData, json_decode($v['data'], TRUE));
        }

        return $processedData;
    }

    public function initDatabase()
    {
        // group providers
        $this->_pdo->exec("
            CREATE TABLE IF NOT EXISTS `providers` (
            `id` VARCHAR(64) NOT NULL,
            `data` TEXT NOT NULL,
            PRIMARY KEY (`id`))
        ");

        // client authorizations
        $this->_pdo->exec("
            CREATE TABLE IF NOT EXISTS `clients` (
            `id` VARCHAR(64) NOT NULL,
            `data` TEXT NOT NULL,
            PRIMARY KEY (`id`))
        ");

    }

}
