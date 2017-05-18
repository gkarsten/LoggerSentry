<?php

/**
 * Class Hackathon_LoggerSentry_Model_Sentry
 */
class Hackathon_LoggerSentry_Model_Sentry extends Zend_Log_Writer_Abstract
{
    /**
     * @var array
     */
    protected $_options = array();

    /**
     * Sentry client
     *
     * @var Raven_Client
     */
    protected $_sentryClient;

    /**
     * @var array
     */
    protected $_priorityToLevelMapping
        = array(
            0 => 'fatal',
            1 => 'fatal',
            2 => 'fatal',
            3 => 'error',
            4 => 'warning',
            5 => 'info',
            6 => 'info',
            7 => 'debug'
        );

    /**
     * Ignore filename - it is Zend_Log_Writer_Abstract dependency
     *
     * @param string $filename
     *
     * @return \Hackathon_LoggerSentry_Model_Sentry
     */
    public function __construct($filename)
    {
        /* @var $helper FireGento_Logger_Helper_Data */
        $helper = Mage::helper('firegento_logger');
        $options = array(
            'logger' => $helper->getLoggerConfig('sentry/logger_name')
        );
        try {
            $this->_sentryClient = new Raven_Client($helper->getLoggerConfig('sentry/apikey'), $options);
        } catch (Exception $e) {
            // Ignore errors so that it doesn't crush the website when/if Sentry goes down.
        }
    }

    /**
     * Places event line into array of lines to be used as message body.
     *
     * @param array $eventObj log data event
     * @internal param FireGento_Logger_Model_Event $event Event data
     * @throws Zend_Log_Exception
     * @return void
     *
     */
    protected function _write($eventObj)
    {
        // Check, if Sentry Logger is disabled
        if ((bool)Mage::registry('disable_sentry_logger')) {
            return;
        }

        try {
            /* @var $helper FireGento_Logger_Helper_Data */
            $helper = Mage::helper('firegento_logger');
            $helper->addEventMetadata($eventObj);

            $event = $eventObj->getEventDataArray();

            if($this->isExcludedFromLog($event))
            {
                return $this;
            }

            $additional = array(
                'file' => $event['file'],
                'line' => $event['line'],
            );

            foreach (array('requestUri', 'requestData', 'remoteAddress', 'httpUserAgent') as $key) {
                if (!empty($event[$key])) {
                    $additional[$key] = $event[$key];
                }
            }

            $this->_assumePriorityByMessage($event);

            // if we still can't figure it out, assume it's an error
            $priority = isset($event['priority']) && !empty($event['priority']) ? $event['priority'] : 3;

            if (!$this->_isHighEnoughPriorityToReport($priority)) {
                return; // Don't log anything warning or less severe.
            }

            $data = array();
            $data['level'] = $this->_priorityToLevelMapping[$priority];
            $data['tags'] = $this->_getTagsData();
            if ($this->_isSessionDataAvailable($additional['file'])) {
                $data['user'] = $this->_getUserData();
            }

            $this->_sentryClient->captureMessage($event['message'], array(), $data, true, $additional);

        } catch (Exception $e) {
            throw new Zend_Log_Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @return bool
     */
    protected function _isSessionDataAvailable($eventFile)
    {
        return (strpos($eventFile, 'Cm/RedisSession') === false);
    }

    /**
     * @return array
     */
    protected function _getUserData()
    {
        $user = array();

        try {
            $adminSession = Mage::getSingleton('admin/session');
            $customerSession = Mage::getModel('customer/session');

            $user = array(
                // Admin data
                'isLoggedInBackend'  => $adminSession->isLoggedIn(),
                'adminId'            => $adminSession->isLoggedIn() ? $adminSession->getUser()->getId() : null,
                'adminUsername'      => $adminSession->isLoggedIn() ? $adminSession->getUser()->getUsername() : null,
                // Customer data
                'isLoggedInFrontend' => $customerSession->isLoggedIn(),
                'customerId'         => $customerSession->getCustomerId(),
                'customerGroupId'    => $customerSession->getCustomerGroupId(),
                'customerName'       => $customerSession->getCustomerId() ? $customerSession->getCustomer()->getName() : null,
                'customerEmail'      => $customerSession->getCustomerId() ? $customerSession->getCustomer()->getEmail() : null,
            );
        } catch (Exception $e) {
            // Ignore errors
        }

        return $user;
    }

    /**
     * @return array
     */
    protected function _getTagsData()
    {
        $tags = array();

        try {
            $cronTask = Mage::registry('current_cron_task');
            $tags = array(
                'cron_task'          => $cronTask ? true : false,
                'cron_task_job_code' => $cronTask ? $cronTask->getJobCode() : null,
            );
        } catch (Exception $e) {
            // Ignore errors
        }

        return $tags;
    }

    /**
     * @param  int $priority
     * @return boolean           True if we should be reporting this, false otherwise.
     */
    protected function _isHighEnoughPriorityToReport($priority)
    {
        if ($priority > (int)Mage::helper('firegento_logger')->getLoggerConfig('sentry/priority')) {
            return false; // Don't log anything warning or less severe than configured.
        }
        return true;
    }

    /**
     * Try to attach a priority # based on the error message string (since sometimes it is not specified)
     * @param FireGento_Logger_Model_Event &$event Event data
     * @return \Hackathon_LoggerSentry_Model_Sentry
     */
    protected function _assumePriorityByMessage(&$event)
    {
        if (stripos($event['message'], 'warn') === 0) {
            $event['priority'] = 4;
        }
        if (stripos($event['message'], 'notice') === 0) {
            $event['priority'] = 5;
        }

        return $this;
    }

    /**
     * Satisfy newer Zend Framework
     *
     * @static
     *
     * @param $config
     *
     * @return void
     */
    static public function factory($config)
    {
    }

    private function isExcludedFromLog($event)
    {
        try{
            $exceptions = mage::helper('core')->jsonDecode(Mage::getStoreConfig('HackathonExcludedExceptions'));

            foreach($exceptions as $ex){
                if($ex['log'] and $ex['message'] == $event['message'])
                {
                    return true; //don't log to sentry
                }
            }
        }
        catch (Zend_Json_Exception $exception) {
            return false;
        }
        catch (Exception $exception)
        {
            return false;
        }

        return false;
    }
}
