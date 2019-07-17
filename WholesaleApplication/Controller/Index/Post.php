<?php
namespace Magneto\WholesaleApplication\Controller\Index;

use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

class Post extends \Magento\Framework\App\Action\Action
{
    protected $_transportBuilder;

    protected $inlineTranslation;

    protected $scopeConfig;

    protected $storeManager;
   
    protected $_escaper;

    protected $fileSystem;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Escaper $escaper,
        Filesystem $fileSystem
    ) {
        parent::__construct($context);
        $this->fileSystem = $fileSystem;
        $this->_transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->_escaper = $escaper;
    }

    public function execute()
    {
        $post = $this->getRequest()->getPost();
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        
        if (!$post) {
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            return $resultRedirect;
        }

        if ($_FILES['image']['name']) {
            try {
                // init uploader model.
                $uploader = $this->_objectManager->create(
                    'Magento\MediaStorage\Model\File\Uploader',
                    ['fileId' => 'image']
                );
                $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
                $uploader->setAllowRenameFiles(true);
                $uploader->setFilesDispersion(true);
                // get media directory
                $mediaDirectory = $this->fileSystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath('wholesale');
                // save the image to media directory
                $result = $uploader->save($mediaDirectory);
                $baseUrl = $this->storeManager->getStore()->getBaseUrl();
                $fileUrl = $baseUrl.'pub/media/wholesale/'.$result['file'];
                
            } catch (Exception $e) {
                \Zend_Debug::dump($e->getMessage());
            }
        }

        $this->inlineTranslation->suspend();
        
        try
        {
            $sender = [
                'name' => $this->_escaper->escapeHtml($post['name']),
                'email' => $this->_escaper->escapeHtml($post['email']),
            ];
            $to = array($this->scopeConfig->getValue('trans_email/ident_support/email',ScopeInterface::SCOPE_STORE));
            $templateOptions = array('area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $this->storeManager->getStore()->getId());
            $templateVars = array(
                    'name' => $post['name'],
                    'cname' => $post['cname'],
                    'email' => $post['email'],
                    'address' => $post['address'],
                    'address2' => $post['address2'],
                    'city' => $post['city'],
                    'state' => $post['state'],
                    'zipcode' => $post['zipcode'],
                    'country' => $post['country'],
                    'areacode' => $post['areacode'],
                    'phonenumber' => $post['phonenumber'],
                    'website' => $post['website'],
                    'reseller' => $post['reseller'],
                    'description' => $post['description'],
                    'additional' => $post['additional'],
                    'file' => $post['image'],
                );
            
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE; 

            $transport = $this->_transportBuilder
            ->setTemplateIdentifier('custom_mail_template')
            ->setTemplateOptions($templateOptions)
            ->setTemplateVars($templateVars)
            ->setFrom($sender)
            ->addTo($to)
            ->getTransport();
            $transport->sendMessage();

            $this->inlineTranslation->resume();
            $this->messageManager->addSuccess(
                __('Thanks for contacting us with your comments and questions. We\'ll respond to you very soon.')
            );
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            return $resultRedirect;
        } catch(\Exception $e){
            $this->inlineTranslation->resume();
            $this->messageManager->addError(__('We can\'t process your request right now. Sorry, that\'s all we know.'.$e->getMessage())
            );
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            return $resultRedirect;
        }
    }
}