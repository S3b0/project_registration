<?php
namespace S3b0\ProjectRegistration\Controller;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2015 Sebastian Iffland <sebastian.iffland@ecom-ex.com>, ecom instruments GmbH
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
use S3b0\ProjectRegistration\Domain\Model;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Utility as CoreUtility;

/**
 * ProjectController
 */
class ProjectController extends RepositoryInjectionController
{

    /**
     * action list
     *
     * @return void
     */
    public function listAction()
    {
        $projects         = $this->projectRepository->findAll();
        $addressees       = $this->getAddressees();
        $loggedInUserRole = 0;

        if ($this->getTypoScriptFrontendController()->loginUser) {
            if ((int) $this->settings['investigator'] === (int) $this->getTypoScriptFrontendController()->fe_user->user[ $this->getTypoScriptFrontendController()->fe_user->userid_column ]) {
                $loggedInUserRole = 1;
            }
            if ((int) $this->settings['admin'] === (int) $this->getTypoScriptFrontendController()->fe_user->user[ $this->getTypoScriptFrontendController()->fe_user->userid_column ]) {
                $loggedInUserRole = 2;
            }
        }

        if ($loggedInUserRole < 1) {
              // @todo activate on finish
            /*$this->getTypoScriptFrontendController()->pageNotFoundAndExit();*/
        }

        $this->view->assignMultiple([
            'projects'         => $projects,
            'addressees'       => $addressees,
            'loggedInUserRole' => $loggedInUserRole
        ]);
    }

    public function initializeShowAction()
    {
        $this->manuallySetProjectArgument();
    }

    /**
     * action show
     *
     * @param \S3b0\ProjectRegistration\Domain\Model\Project $project
     *
     * @return void
     */
    public function showAction(Model\Project $project)
    {
        $this->view->assign('project', $project);
    }

    /**
     * action new
     *
     * @return void
     */
    public function newAction()
    {
        $newProject = new Model\Project();
        $newRegistrant = new Model\Person();
        $newEndUser = new Model\Person();
        $addressees = $this->getAddressees();
        if ($this->getTypoScriptFrontendController()->loginUser) {
            /** @var \Ecom\EcomToolbox\Domain\Model\User $feUser */
            $feUser = $this->frontendUserRepository->findByUid($this->getTypoScriptFrontendController()->fe_user->user[ $this->getTypoScriptFrontendController()->fe_user->userid_column ]);
            $newRegistrant = $this->personRepository->findOneByFeUser($feUser) ?: new Model\Person($feUser);
        }

        ksort($addressees);
        $this->view->assignMultiple([
            'dto'                      => new Model\Dto\ProjectPersonsDto($newProject, $newRegistrant, $newEndUser),
            'feUser'                   => $this->getTypoScriptFrontendController()->loginUser,
            'products'                 => $this->productRepository->findAll(),
            'countries'                => $this->regionRepository->findByType(0),
            'states'                   => $this->stateRepository->findAll(),
            'addressees'               => $addressees,
            'projectProductProperties' => $this->productPropertyRepository->findAll()
        ]);
    }

    /**
     * Initializes the controller before invoking create method.
     *
     * @return void
     */
    protected function initializeCreateAction()
    {
        $propertyMappingConfiguration = $this->arguments[ 'dto' ]->getPropertyMappingConfiguration();
        $propertyMappingConfiguration->allowAllProperties();
        if ($dto = $this->request->getArgument('dto')) {
            $dto[ 'project' ][ 'estimatedPurchaseDate' ] = date(\DateTime::W3C,
                strtotime($dto[ 'project' ][ 'estimatedPurchaseDate' ]));
            $this->request->setArgument('dto', $dto);
        }
    }

    /**
     * action create
     *
     * @param \S3b0\ProjectRegistration\Domain\Model\Dto\ProjectPersonsDto $dto
     * @ignorevalidation $dto
     *
     * @dontverifyrequesthash
     *
     * @return void
     */
    public function createAction(Model\Dto\ProjectPersonsDto $dto) {
        // Add endUser to personRepository
        $this->personRepository->add($dto->getEndUser());
        // Add registrant to personRepository (if not existing feUser reference)
        if ($dto->getRegistrant()
                ->getFeUser() instanceof \Ecom\EcomToolbox\Domain\Model\User
        ) {
            $registrant = $this->personRepository->findOneByFeUser($dto->getRegistrant()
                                                                       ->getFeUser());
            if ($registrant instanceof Model\Person) {
                $dto->setRegistrant($registrant);
            } else {
                $dto->setRegistrant(new Model\Person($dto->getRegistrant()
                                                         ->getFeUser()));
                $this->personRepository->add($dto->getRegistrant());
            }
        } else {
            $this->personRepository->add($dto->getRegistrant());
        }
        /** @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager $persistenceManager */
        $persistenceManager = CoreUtility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class);
        $persistenceManager->persistAll();
        // Add property values
        $propertyValues = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        if (is_array($dto->getPropertyValues()) && count($dto->getPropertyValues())) {
            foreach ($dto->getPropertyValues() as $propertyValue) {
                if (($value = $this->productPropertyValueRepository->findByUid($propertyValue)) instanceof Model\ProductPropertyValue) {
                    $propertyValues->attach($value);
                }
            }
        }
        $project = $dto->getProject();
        $project->setRegistrant($dto->getRegistrant());
        $project->setEndUser($dto->getEndUser());
        $project->setPropertyValues($propertyValues);
        $this->projectRepository->add($project);
        $persistenceManager->persistAll();

        $noReply = null;
        if ($this->settings[ 'mail' ][ 'noReplyEmail' ] && CoreUtility\GeneralUtility::validEmail($this->settings[ 'mail' ][ 'noReplyEmail' ]) && $this->settings[ 'mail' ][ 'senderName' ]) {
            $noReply = [$this->settings[ 'mail' ][ 'noReplyEmail' ] => $this->settings[ 'mail' ][ 'senderName' ]];
        }
        $sender = $this->getAddressees(true, $project->getAddressee()) ?: CoreUtility\MailUtility::getSystemFrom();
        $carbonCopyReceivers = [];
        if ($this->settings[ 'mail' ][ 'carbonCopy' ]) {
            foreach (explode(',', $this->settings[ 'mail' ][ 'carbonCopy' ]) as $carbonCopyReceiver) {
                $tokens = CoreUtility\GeneralUtility::trimExplode(' ', $carbonCopyReceiver, true, 2);
                if (CoreUtility\GeneralUtility::validEmail($tokens[ 0 ])) {
                    $carbonCopyReceivers[ $tokens[ 0 ] ] = $tokens[ 1 ];
                }
            }
        }

        /** @var \TYPO3\CMS\Core\Mail\MailMessage $mailToSender */
        $mailToSender = CoreUtility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Mail\MailMessage::class);
        $mailToSender->setContentType('text/html');

        /**
         * Email to sender
         */
        $mailToSender->setFrom($noReply ?: $sender)
                     ->setTo([
                         $dto->getRegistrant()
                             ->getEmail() => $dto->getRegistrant()
                                                 ->getName()
                     ])
                        /** @todo localize! */
                     ->setSubject($this->settings[ 'mail' ][ 'projectRegisteredConfirmationSubject' ] ?: (LocalizationUtility::translate('mail.projectRegisteredConfirmation.subject', $this->extensionName) ?: 'You project registration request -- DEV!'))
                     ->setBody($this->getStandAloneTemplate(
                         CoreUtility\ExtensionManagementUtility::siteRelPath(CoreUtility\GeneralUtility::camelCaseToLowerCaseUnderscored($this->extensionName)) . 'Resources/Private/Templates/Email/ProjectRegisteredConfirmation.html',
                         [
                             'settings'   => $this->settings,
                             'submitted'  => $dto,
                             'addressees' => $this->getAddressees()
                         ]
                     ))
                     ->send();
#        $this->internalRedirect('confirmation');
    }

    /**
     * action confirmation
     *
     * @return void
     */
    public function confirmationAction()
    {

    }

    /**
     * @return void
     */
    public function initializeEditAction()
    {
        $this->manuallySetProjectArgument();
    }

    /**
     * action edit
     *
     * @param \S3b0\ProjectRegistration\Domain\Model\Project $project
     * @ignorevalidation $project
     *
     * @return void
     */
    public function editAction(
        Model\Project $project
    ) {
        $this->view->assign('project', $project);
    }

    /**
     * @return void
     */
    public function initializeUpdateAction()
    {
        $this->manuallySetProjectArgument();
    }

    /**
     * action update
     *
     * @param \S3b0\ProjectRegistration\Domain\Model\Project $project
     *
     * @return void
     */
    public function updateAction(
        Model\Project $project
    ) {
        $this->updateRecord($project, "Project with #{$project->getUid()} was updated!", \TYPO3\CMS\Core\Messaging\AbstractMessage::OK, self::NEUTER_ARTICLE, true);
        $this->redirect('list');
    }

    /**
     * @return void
     */
    public function initializeDeleteAction()
    {
        $this->manuallySetProjectArgument();
    }

    /**
     * action delete
     *
     * @param \S3b0\ProjectRegistration\Domain\Model\Project $project
     *
     * @return void
     */
    public function deleteAction(
        Model\Project $project
    ) {
        if ($project->isVisible()) {
            $this->deleteRecord($project, "Project with #{$project->getUid()} was deleted!", \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR, self::NEUTER_ARTICLE, true);
        }
        $this->internalRedirect('list');
    }

    /**
     * @return void
     */
    public function initializeAcceptAction()
    {
        $this->manuallySetProjectArgument();
    }

    /**
     * action accept
     *
     * @param \S3b0\ProjectRegistration\Domain\Model\Project $project
     *
     * @return void
     */
    public function acceptAction(Model\Project $project)
    {
        $project->setHidden(false);
        $project->setApproved(true);
        $this->updateRecord($project, "Project with #{$project->getUid()} was approved!", \TYPO3\CMS\Core\Messaging\AbstractMessage::OK, self::NEUTER_ARTICLE, true);
        // @todo: mail to registrant with status update
        $this->internalRedirect('list');
    }

    /**
     * @return void
     */
    public function initializeRejectAction()
    {
        $this->manuallySetProjectArgument();
    }

    /**
     * action reject
     *
     * @param \S3b0\ProjectRegistration\Domain\Model\Project $project
     *
     * @return void
     */
    public function rejectAction(Model\Project $project)
    {
        $project->setHidden(false);
        $project->setApproved(false);
        $this->updateRecord($project, "Project with #{$project->getUid()} was rejected!", \TYPO3\CMS\Core\Messaging\AbstractMessage::WARNING, self::NEUTER_ARTICLE, true);
        // @todo: mail to registrant with status update
        $this->internalRedirect('list');
    }

    /**
     * @return void
     */
    public function initializeAddInternalNoteAction()
    {
        $this->manuallySetProjectArgument();
    }

    /**
     * action addInternalNote
     *
     * @return void
     */
    public function addInternalNoteAction(Model\Project $project)
    {
        if ($project->_isDirty()) {
            $this->updateRecord($project, "Project with #{$project->getUid()} was updated!", \TYPO3\CMS\Core\Messaging\AbstractMessage::INFO, self::NEUTER_ARTICLE, true);
            $this->internalRedirect('list');
        }
        $this->view->assign('project', $project);
    }

    /**
     * @return void
     */
    public function initializeAddDenialNoteAction()
    {
        $this->manuallySetProjectArgument();
    }

    /**
     * action addDenialNote
     *
     * @return void
     */
    public function addDenialNoteAction(Model\Project $project)
    {
        $this->view->assign('project', $project);
    }

    /**
     * manuallySetProjectArgument action.
     * Needed since working with hidden records displayed too, but handling them is not supported by the core! Our
     * repository does this job.
     *
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\InvalidArgumentNameException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     */
    public function manuallySetProjectArgument()
    {
        if (!$this->request->getArgument('project') instanceof Model\Project) {
            if (CoreUtility\MathUtility::canBeInterpretedAsInteger($this->request->getArgument('project'))) {
                $this->request->setArgument('project', $this->projectRepository->findByUid($this->request->getArgument('project')));
            } elseif (is_array($this->request->getArgument('project')) && array_key_exists('__identity', $this->request->getArgument('project'))) {
                /** @var \S3b0\ProjectRegistration\Domain\Model\Project $project */
                $project = $this->projectRepository->findByUid($this->request->getArgument('project')['__identity']);
                foreach ((array) $this->request->getArgument('project') as $property => $propertyValue) {
                    if ($project->_hasProperty($property)) {
                        $project->_setProperty($property, $propertyValue);
                    }
                }
                $this->request->setArgument('project', $project);
            }
        }
    }

    /**
     * @param bool $returnMails     If set, mails will be returned, pre-formatted for use with
     *                              \TYPO3\CMS\Core\Mail\MailMessage
     * @param int  $returnArrayItem If set, a single array item will be returned
     *
     * @return array|string
     */
    private function getAddressees(
        $returnMails = false,
        $returnArrayItem = null
    ) {
        $return = [];

        if (is_array($this->settings[ 'addressees' ][ 'data' ]) && sizeof($this->settings[ 'addressees' ][ 'data' ])) {
            foreach ($this->settings[ 'addressees' ][ 'data' ] as $k => $addressee) {
                if ($addressee[ 'inactive' ]) {
                    continue;
                }
                if (($label = LocalizationUtility::translate($addressee[ 'label' ], $this->extensionName)) && CoreUtility\GeneralUtility::validEmail($addressee[ 'mail' ])
                ) {
                    $return[ $k ] = $returnMails ? ($addressee[ 'name' ] ? [$addressee[ 'mail' ] => $addressee[ 'name' ]] : [$addressee[ 'mail' ]]) : $label;
                }
            }
        }

        if (is_integer($returnArrayItem) && array_key_exists($returnArrayItem, $return)) {
            return $return[ $returnArrayItem ];
        } else {
            return $return;
        }
    }

    /**
     * @param string $templateFile template name (UpperCamelCase)
     * @param mixed  $data         variables to be passed to the Fluid view
     *
     * @return string
     */
    protected function getStandAloneTemplate($templateFile, $data)
    {
        /** @var \TYPO3\CMS\Fluid\View\StandaloneView $view */
        $view = $this->objectManager->get(\TYPO3\CMS\Fluid\View\StandaloneView::class);

        $templatePathAndFilename = $templateFile;
        $view->setTemplatePathAndFilename($templatePathAndFilename);
        $view->setControllerContext($this->controllerContext);
        $view->assign('data', $data);
        $view->setFormat('html');

        return \Ecom\EcomToolbox\Utility\Div::sanitize_output($view->render());
    }

}