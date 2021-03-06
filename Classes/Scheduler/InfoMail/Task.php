<?php
namespace S3b0\ProjectRegistration\Scheduler\InfoMail;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Sebastian Iffland <Sebastian.Iffland@ecom-ex.com>, ecom instruments GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class Task
 */
class Task extends \TYPO3\CMS\Scheduler\Task\AbstractTask
{

    /**
     * The sender email address
     * @var string $senderAddress
     */
    protected $senderAddress = '';

    /**
     * The receiver email address
     * @var string $receiverAddress
     */
    protected $receiverAddress = '';

    /**
     * @return bool
     */
    public function execute()
    {
        /** @var \S3b0\ProjectRegistration\Scheduler\InfoMail\BusinessLogic $businessLogic */
        $businessLogic = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\S3b0\ProjectRegistration\Scheduler\InfoMail\BusinessLogic::class);
        return $businessLogic->run($this);
    }

    /**
     * @return string
     */
    public function getSenderAddress()
    {
        return $this->senderAddress;
    }

    /**
     * @param string $senderAddress
     */
    public function setSenderAddress($senderAddress)
    {
        $this->senderAddress = $senderAddress;
    }

    /**
     * @return string
     */
    public function getReceiverAddress()
    {
        return $this->receiverAddress;
    }

    /**
     * @param string $receiverAddress
     */
    public function setReceiverAddress($receiverAddress)
    {
        $this->receiverAddress = $receiverAddress;
    }

}