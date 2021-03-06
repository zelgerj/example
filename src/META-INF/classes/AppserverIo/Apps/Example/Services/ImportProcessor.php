<?php

/**
 * AppserverIo\Apps\Example\Services\ImportProcessor
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io-apps/example
 * @link      http://www.appserver.io
 */

namespace AppserverIo\Apps\Example\Services;

use AppserverIo\Lang\Boolean;
use AppserverIo\Messaging\MessageQueue;
use AppserverIo\Messaging\StringMessage;
use AppserverIo\Messaging\QueueConnectionFactory;
use AppserverIo\Psr\HttpMessage\PartInterface;

/**
 * A SLSB that handles the import process.
 *
 * @author    Tim Wagner <tw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io-apps/example
 * @link      http://www.appserver.io
 *
 * @Stateless
 */
class ImportProcessor extends AbstractPersistenceProcessor implements ImportProcessorInterface
{

    /**
     * Returns an ArrayObject with the CSV files that can be imported.
     *
     * @return \ArrayObject An array with the name of CSV files that can be imported
     */
    public function findAll()
    {

        // initialize an array object to load file uploads
        $overviewData = new \ArrayObject();

        // init file iterator on deployment directory
        $fileIterator = new \FilesystemIterator(ini_get('upload_tmp_dir'));

        // Iterate through all phar files and extract them to tmp dir
        foreach (new \RegexIterator($fileIterator, '/^.*\\.csv$/') as $importFile) {
            $overviewData->append($importFile->getFilename());
        }

        // return the array with the name of the uploaded files
        return $overviewData;
    }

    /**
     * Uploads the passed file part to the temporary upload directory.
     *
     * @param \AppserverIo\Psr\HttpMessage\PartInterface $fileToUpload   The file part to upload
     * @param \AppserverIo\Lang\Boolean                  $watchDirectory TRUE if the directory has to be watched
     *
     * @return void
     */
    public function upload(PartInterface $fileToUpload, Boolean $watchDirectory)
    {

        // save file to appservers upload tmp folder with tmpname
        $fileToUpload->init();
        $fileToUpload->write(
            tempnam(ini_get('upload_tmp_dir'), 'example_upload_') . '.' . pathinfo($fileToUpload->getFilename(), PATHINFO_EXTENSION)
        );

        // check if we should watch the directory for periodic import
        if ($watchDirectory->booleanValue()) {
            // load the application name
            $applicationName = $this->getApplication()->getName();

            // initialize the connection and the session
            $queue = MessageQueue::createQueue('pms/createAIntervalTimer');
            $connection = QueueConnectionFactory::createQueueConnection($applicationName);
            $session = $connection->createQueueSession();
            $sender = $session->createSender($queue);

            // initialize the message with the name of the directory we want to watch
            $message = new StringMessage(ini_get('upload_tmp_dir'));

            // create a new message and send it
            $sender->send($message, false);
        }
    }

    /**
     * Delete the file from the temporary upload directory
     *
     * @param string $filename The name of the file to upload
     *
     * @return void
     */
    public function delete($filename)
    {
        unlink(ini_get('upload_tmp_dir') . DIRECTORY_SEPARATOR . $filename);
    }

    /**
     * Import the file with the passed filename from the temporary upload directory.
     *
     * @param string $filename The name of the file to import
     *
     * @return void
     */
    public function import($filename)
    {

        // load the application name
        $applicationName = $this->getApplication()->getName();

        // initialize the connection and the session
        $queue = MessageQueue::createQueue('pms/import');
        $connection = QueueConnectionFactory::createQueueConnection($applicationName);
        $session = $connection->createQueueSession();
        $sender = $session->createSender($queue);

        // initialize the message with the name of the file to import the data from
        $message = new StringMessage(ini_get('upload_tmp_dir') . DIRECTORY_SEPARATOR . $filename);

        // create a new message and send it
        $sender->send($message, false);
    }
}
