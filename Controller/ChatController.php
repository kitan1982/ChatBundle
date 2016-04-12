<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\ChatBundle\Controller;

use Claroline\ChatBundle\Entity\ChatRoom;
use Claroline\ChatBundle\Entity\ChatRoomMessage;
use Claroline\ChatBundle\Form\ChatRoomConfigurationType;
use Claroline\ChatBundle\Manager\ChatManager;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler;
use Claroline\CoreBundle\Library\Security\Collection\ResourceCollection;
use JMS\DiExtraBundle\Annotation as DI;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Translation\TranslatorInterface;

class ChatController extends Controller
{
    private $authorization;
    private $chatManager;
    private $formFactory;
    private $platformConfigHandler;
    private $request;
    private $router;
    private $tokenStorage;
    private $translator;

    /**
     * @DI\InjectParams({
     *     "authorization"         = @DI\Inject("security.authorization_checker"),
     *     "chatManager"           = @DI\Inject("claroline.manager.chat_manager"),
     *     "formFactory"           = @DI\Inject("form.factory"),
     *     "platformConfigHandler" = @DI\Inject("claroline.config.platform_config_handler"),
     *     "requestStack"          = @DI\Inject("request_stack"),
     *     "router"                = @DI\Inject("router"),
     *     "tokenStorage"          = @DI\Inject("security.token_storage"),
     *     "translator"            = @DI\Inject("translator")
     * })
     */
    public function __construct(
        AuthorizationCheckerInterface $authorization,
        ChatManager $chatManager,
        FormFactory $formFactory,
        PlatformConfigurationHandler $platformConfigHandler,
        RequestStack $requestStack,
        RouterInterface $router,
        TokenStorageInterface $tokenStorage,
        TranslatorInterface $translator
    )
    {
        $this->authorization = $authorization;
        $this->chatManager = $chatManager;
        $this->formFactory = $formFactory;
        $this->platformConfigHandler = $platformConfigHandler;
        $this->request = $requestStack->getCurrentRequest();
        $this->router = $router;
        $this->tokenStorage = $tokenStorage;
        $this->translator = $translator;
    }

    /**
     * @EXT\Route(
     *     "/user/{user}/chat",
     *     name="claro_chat_user",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template()
     */
    public function userChatAction(User $authenticatedUser, User $user)
    {
        $xmppHost = $this->platformConfigHandler->getParameter('chat_xmpp_host');
        $boshPort = $this->platformConfigHandler->getParameter('chat_bosh_port');
        $chatUser = $this->chatManager->getChatUserByUser($authenticatedUser);

        return array(
            'chatUser' => $chatUser,
            'user' => $user,
            'xmppHost' => $xmppHost,
            'boshPort' => $boshPort
        );
    }

    /**
     * @EXT\Route(
     *     "/chat/room/{chatRoom}/open",
     *     name="claro_chat_room_open",
     *     options={"expose"=true}
     * )
     * @EXT\Template()
     */
    public function chatRoomOpenAction(ChatRoom $chatRoom)
    {
        $this->checkChatRoomRight($chatRoom, 'OPEN');
        $this->chatManager->initChatRoom($chatRoom);
        $user = $this->tokenStorage->getToken()->getUser();

        if ($user === 'anon.' || $chatRoom->getRoomStatus() === ChatRoom::CLOSED) {

            return new RedirectResponse(
                $this->router->generate(
                    'claro_chat_room_archives',
                    array('chatRoom' => $chatRoom->getId())
                )
            );
        }
        $xmppHost = $this->platformConfigHandler->getParameter('chat_xmpp_host');
        $xmppMucHost = $this->platformConfigHandler->getParameter('chat_xmpp_muc_host');
        $boshPort = $this->platformConfigHandler->getParameter('chat_bosh_port');
        $iceServers = $this->platformConfigHandler->getParameter('chat_ice_servers');
        $chatAdminUsername = $this->platformConfigHandler->getParameter('chat_admin_username');
        $chatAdminPassword = $this->platformConfigHandler->getParameter('chat_admin_password');
        $chatUser = $this->chatManager->getChatUserByUser($user);
        $canChat = !is_null($chatUser);
        $canEdit = $this->hasChatRoomRight($chatRoom, 'EDIT');
        $color = null;
        
        if (!is_null($chatUser)) {
            $options = $chatUser->getOptions();

            if (is_array($options) && isset($options['color'])) {
                $color = $options['color'];
            }
        }
        $hasAdmin = !empty($chatAdminUsername) && !empty($chatAdminPassword);

        return array(
            'workspace' => $chatRoom->getResourceNode()->getWorkspace(),
            'canChat' => $canChat,
            'canEdit' => $canEdit,
            'chatUser' => $chatUser,
            'chatRoom' => $chatRoom,
            'xmppHost' => $xmppHost,
            'xmppMucHost' => $xmppMucHost,
            'boshPort' => $boshPort,
            'iceServers' => $iceServers,
            'color' => $color,
            'hasAdmin' => $hasAdmin,
            'chatAdminUsername' => $chatAdminUsername,
            'chatAdminPassword' => $chatAdminPassword
        );
    }

    /**
     * @EXT\Route(
     *     "/chat/room/{chatRoom}/archives",
     *     name="claro_chat_room_archives",
     *     options={"expose"=true}
     * )
     * @EXT\Template()
     */
    public function chatRoomArchivesAction(ChatRoom $chatRoom)
    {
        $this->checkChatRoomRight($chatRoom, 'OPEN');
        $canEdit = $this->hasChatRoomRight($chatRoom, 'EDIT');
        $messages = $chatRoom->getMessages();
        $messagesDatas = array();

        foreach ($messages as $message) {
            $creationDate = $message->getCreationDate();
            $day = $creationDate->format('d/m/Y');

            if (!isset($messagesDatas[$day])) {
                $messagesDatas[$day] = array();
            }
            $messagesDatas[$day][] = $message;
        }
        $users = $this->chatManager->getChatRoomParticipantsName($chatRoom);

        return array(
            'chatRoom' => $chatRoom,
            'canEdit' => $canEdit,
            'messagesDatas' => $messagesDatas,
            'users' => $users
        );
    }

    /**
     * @EXT\Route(
     *     "/chat/room/{chatRoom}/archives/retrieve",
     *     name="claro_chat_room_archives_retrieve",
     *     options={"expose"=true}
     * )
     * @EXT\Template()
     */
    public function chatRoomArchivesRetrieveAction(ChatRoom $chatRoom)
    {
        $this->checkChatRoomRight($chatRoom, 'OPEN');
        $messages = $chatRoom->getMessages();
        $messagesDatas = array();

        foreach ($messages as $message) {
            $messagesDatas[] = array(
                'username' => $message->getUsername(),
                'userFullName' => $message->getUserFullName(),
                'content' => $message->getContent(),
                'type' => $message->getType()
            );
        }

        return new JsonResponse($messagesDatas, 200);
    }

    /**
     * @EXT\Route(
     *     "/chat/room/{chatRoom}/user/{username}/full/{fullName}/message/{message}",
     *     name="claro_chat_room_message_register",
     *     defaults={"message"=""},
     *     requirements={"message"=".+"},
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     */
    public function chatRoomMessageRegisterAction(
        ChatRoom $chatRoom,
        $username,
        $fullName,
        $message = ''
    )
    {
        $this->checkChatRoomRight($chatRoom, 'OPEN');
        $this->chatManager->saveChatRoomMessage(
            $chatRoom,
            $username,
            $fullName,
            $message,
            ChatRoomMessage::MESSAGE
        );

        return new JsonResponse('success', 200);
    }

    /**
     * @EXT\Route(
     *     "/chat/room/{chatRoom}/user/{username}/full/{fullName}/presence/status/{status}",
     *     name="claro_chat_room_presence_register",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     */
    public function chatRoomPresenceRegisterAction(
        ChatRoom $chatRoom,
        $username,
        $fullName,
        $status
    )
    {
        $this->checkChatRoomRight($chatRoom, 'OPEN');
        $this->chatManager->saveChatRoomMessage(
            $chatRoom,
            $username,
            $fullName,
            $status,
            ChatRoomMessage::PRESENCE
        );

        return new JsonResponse('success', 200);
    }

    /**
     * @EXT\Route(
     *     "/chat/room/{chatRoom}/user/{username}/full/{fullName}/status/status/{status}",
     *     name="claro_chat_room_status_register",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     */
    public function chatRoomStatusRegisterAction(
        ChatRoom $chatRoom,
        $username,
        $fullName,
        $status
    )
    {
        $this->checkChatRoomRight($chatRoom, 'EDIT');
        $this->chatManager->saveChatRoomMessage(
            $chatRoom,
            $username,
            $fullName,
            $status,
            ChatRoomMessage::STATUS
        );

        return new JsonResponse('success', 200);
    }

    /**
     * @EXT\Route(
     *     "/chat/room/{chatRoom}/configure/form",
     *     name="claro_chat_room_configure_form",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineChatBundle:Chat:chatRoomConfigureModalForm.html.twig")
     */
    public function chatRoomConfigureFormAction(ChatRoom $chatRoom)
    {
        $this->checkChatRoomRight($chatRoom, 'EDIT');
        $form = $this->formFactory->create(
            new ChatRoomConfigurationType($this->platformConfigHandler),
            $chatRoom
        );
        $xmppMucHost = $this->platformConfigHandler->getParameter('chat_xmpp_muc_host');

        return array(
            'form' => $form->createView(),
            'chatRoom' => $chatRoom,
            'xmppMucHost' => $xmppMucHost
        );
    }

    /**
     * @EXT\Route(
     *     "/chat/room/{chatRoom}/configure",
     *     name="claro_chat_room_configure",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     * @EXT\Template("ClarolineChatBundle:Chat:chatRoomConfigureModalForm.html.twig")
     */
    public function chatRoomConfigureAction(ChatRoom $chatRoom)
    {
        $this->checkChatRoomRight($chatRoom, 'EDIT');
        $form = $this->formFactory->create(
            new ChatRoomConfigurationType($this->platformConfigHandler),
            $chatRoom
        );
        $form->handleRequest($this->request);

        if ($form->isValid()) {
            $this->chatManager->persistChatRoom($chatRoom);

            return new JsonResponse($chatRoom->getRoomStatus(), 200);
        } else {
            $xmppMucHost = $this->platformConfigHandler->getParameter('chat_xmpp_muc_host');

            return array(
                'form' => $form->createView(),
                'chatRoom' => $chatRoom,
                'xmppMucHost' => $xmppMucHost
            );
        }
    }

    /**
     * @EXT\Route(
     *     "/chat/room/{chatRoom}/status/{roomStatus}/edit",
     *     name="claro_chat_room_status_edit",
     *     options={"expose"=true}
     * )
     * @EXT\ParamConverter("authenticatedUser", options={"authenticatedUser" = true})
     */
    public function chatRoomStatusEditAction(ChatRoom $chatRoom, $roomStatus)
    {
        $this->checkChatRoomRight($chatRoom, 'EDIT');
        $chatRoom->setRoomStatus($roomStatus);
        $this->chatManager->persistChatRoom($chatRoom);

        return new JsonResponse('success', 200);
    }

    private function checkChatRoomRight(ChatRoom $chatRoom, $right)
    {
        $collection = new ResourceCollection(array($chatRoom->getResourceNode()));

        if (!$this->authorization->isGranted($right, $collection)) {

            throw new AccessDeniedException($collection->getErrorsForDisplay());
        }
    }

    private function hasChatRoomRight(ChatRoom $chatRoom, $right)
    {
        $collection = new ResourceCollection(array($chatRoom->getResourceNode()));

        return $this->authorization->isGranted($right, $collection);
    }
}
