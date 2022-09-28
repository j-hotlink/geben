<?php
namespace Hotlink\Framework\Trigger;

/**
 * @description This class listens for Magento events, and invokes Interactions.
 */
abstract class AbstractTrigger
    extends \Hotlink\Framework\Interaction\Audit\AbstractAudit
    implements \Magento\Framework\Event\ObserverInterface
{

    /**
     *  @description context keys and names
     *  @return array<string,string>
     */
    abstract public function getContexts() : array;

    /**
     *  @description supported event codes (keys) and human friendly names
     *  @return array
     */
    abstract public function getMagentoEvents() : array;

    /***
     *  @description return the source to use for settings
     */
    abstract public function getSource() : string;

    /**
     *  @description name of trigger used in config and reports
     */
    abstract protected function _getName() : string;

    /***
     *  @description performs security checks and executes required interactions
     */
    abstract protected function _execute( \Hotlink\Framework\Trigger\Settings $settings );

    const KEY_FORBIDDEN_EXCEPTION = "throw_exception_if_forbidden";
    const KEY_INTERACTION         = "interaction";
    const KEY_REPORT              = "report";

    protected \Magento\Framework\DataObjectFactory       $dataFactory;
    protected \Magento\Store\Model\StoreManagerInterface $storeManager;
    protected \Hotlink\Framework\Model\Config\Map        $configMap;
    protected \Hotlink\Framework\Model\UserFactory       $userFactory;
    protected \Hotlink\Framework\Trigger\SettingsFactory $settingsFactory;
    
    protected ?\Hotlink\Framework\Model\User\AbstractUser $user = null;
    protected  bool                                       $executing = false;

    public function __construct( \Hotlink\Framework\Trigger\Context $context )
    {
        parent::__construct( $context );
        $this->dataFactory     = $context->dataFactory;
        $this->storeManager    = $context->storeManager;
        $this->configMap       = $context->configMap;
        $this->userFactory     = $context->userFactory;
        $this->settingsFactory = $context->settingsFactory;
    }

    public function getName() : string
    {
        return __( $this->_getName() );
    }

    protected function getStoreManager() : \Magento\Store\Model\StoreManagerInterface
    {
        return $this->storeManager;
    }

    protected function isSupportedEvent( \Magento\Framework\Event $event ) : bool
    {
        $name = $event->getName();
        $allowed = $this->getMagentoEvents();
        return in_array( $name, $allowed );
    }

    protected function getSettings(
        \Magento\Framework\Event $event,
        string                   $context,
        string                   $source
    ) : \Hotlink\Framework\Trigger\Settings
    {
        return $this->settingsFactory->create( [ 'event'   => $event,
                                                 'context' => $context,
                                                 'source'  => $source ] );
    }
    
    public function isExecuting() : bool
    {
        return $this->executing;
    }

    protected function setExecuting( bool $value ) : self
    {
        $this->executing = $value;
        return $this;
    }

    protected function getEventSource( \Magento\Framework\Event $event )
    {
        return
            \Hotlink\Framework\Trigger\Settings::extractEventSource( $event )
            ?: $this->getSource();
    }

    public function execute( \Magento\Framework\Event\Observer $observer )
    {
        if ( $this->isExecuting() )
            {
                // interactions may load collections (especially frontend) => reentry
                return;
            }

        $event = $observer->getEvent();
        if ( !$this->isSupportedEvent( $event ) )
            {
                $this->getExceptionHelper()
                     ->throwConfiguration( "Event $event is not supported by [class]",
                                           $this );
            }

        if ( $context = $this->getContext( $event ) )
            {
                $source   = $this->getEventSource( $event );
                $settings = $this->getSettings( $event, $context, $source );
                $this->setExecuting( true );
                try
                    {
                        $this->_execute( $settings );
                    }
                catch ( \Exception $e )
                    {
                        throw $e;
                    }
                finally
                    {
                        $this->setExecuting( false );
                    }
            }
    }

    protected function getReportToUse(
        \Hotlink\Framework\Interaction\AbstractInteraction $interaction,
        \Hotlink\Framework\Trigger\Settings                $settings
    ) : \Hotlink\Framework\Model\Report
    {
        return $settings->hasReport()
            ? $settings->getReport()
            : $interaction->getReport();
    }

    protected function init(
        \Hotlink\Framework\Interaction\AbstractInteraction $interaction,
        \Hotlink\Framework\Trigger\Settings                $settings
    )
    {
        $this->initEnvironment( $interaction, $settings );
        $this->initReport( $interaction, $settings );
    }

    /***
     * @description setup environment for interaction
     */
    protected function initEnvironment(
        \Hotlink\Framework\Interaction\AbstractInteraction $interaction,
        \Hotlink\Framework\Trigger\Settings                $settings
    )
    {
        $environment = $interaction->getEnvironment();
        $environment->setTriggerInfo( $this->getName(),
                                      $this->getUser()->getDescription(),
                                      $settings->getEventName(),
                                      $this->getContextName( $settings->getContext() ) );
    }

    protected function isReportInitialised( \Hotlink\Framework\Model\Report $report ) : bool
    {
        return ( !is_null( $report->getUser() )
                 || !is_null( $report->getTrigger() )
                 || !is_null( $report->getContext() )
                 || !is_null( $report->getEvent() ) );
    }

    /***
     * @description setup report for interaction (seldom overloaded)
     */
    protected function initReport(
        \Hotlink\Framework\Interaction\AbstractInteraction $interaction,
        \Hotlink\Framework\Trigger\Settings                $settings
    ) : \Hotlink\Framework\Model\Report
    {
        $report = $this->getReportToUse( $interaction, $settings );
        if ( $this->isReportInitialised( $report ) )
            {
                $report
                    ->trace( "nested trigger" )
                    ->indent()
                    ->trace( "name : "    . $this->getName() )
                    ->trace( "user : "    . $this->getUser()->getDescription() )
                    ->trace( "context : " . $this->getContextName( $settings->getContext() ) )
                    ->trace( "event : "   . $settings->getName() )
                    ->unindent();
            }

        if ( is_null( $report->getUser() ) )
            {
                $report->setUser( $this->getUser()->getDescription() );
            }

        if ( is_null( $report->getProcess() ) )
            {
                // only set the outer-most interaction name
                $report->setProcess( $interaction->getName() );
            }

        if ( is_null( $report->getTrigger() ) )
            {
                $report->setTrigger( $this->getName() );
            }

        if ( is_null( $report->getContext() ) )
            {
                $report->setContext( $this->getContextName( $settings->getContext() ) );
            }

        if ( is_null( $report->getEvent() ) )
            {
                $report->setEvent( $settings->getEventName() );
            }

        $report
            ->addLogWriter()
            ->addItemWriter()
            ->addDataWriter();

        return $report;
    }

    public function getContext( \Magento\Framework\Event $event ) : ?string
    {
        return $event->getName();
    }

    public function getUser() : \Hotlink\Framework\Model\User\AbstractUser
    {
        if ( is_null ( $this->user ) )
            {
                $this->user = $this->userFactory->create();
            }
        return $this->user;
    }

    public function getContextName( string $context ) : string
    {
        $contexts = $this->getContexts();
        if ( isset( $contexts[ $context ] ) )
            {
                return $contexts[ $context ];
            }
        return '*unknown*';
    }

    //
    //  IReport
    //
    public function getReportSection() : string
    {
        return 'trigger';
    }

}
