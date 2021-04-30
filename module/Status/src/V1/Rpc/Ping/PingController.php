<?php
/**
 * PingController.php - Ping Controller
 *
 * Main Controller for Status Ping
 *
 * @category Controller
 * @package Status
 * @author Praesidiarius
 * @copyright (C) 2021 Praesidiarius <admin@1plc.ch>
 * @license https://opensource.org/licenses/BSD-3-Clause
 * @version 1.1.0
 * @since 1.1.0
 */

namespace Status\V1\Rpc\Ping;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ApiTools\ContentNegotiation\ViewModel;

class PingController extends AbstractActionController
{
    /**
     * Check if API is up and running
     *
     * @return ViewModel
     * @since 1.1.0
     */
    public function pingAction()
    {
        return new ViewModel([
            'ack' => time()
        ]);
    }
}
