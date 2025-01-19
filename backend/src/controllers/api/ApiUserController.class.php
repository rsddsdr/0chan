<?php

class ApiUserController extends ApiBaseController
{
    const LOGIN_MIN_LENGTH = 5;
    const PASSWORD_MIN_LENGTH = 8;

    /**
     * @Auth
     * @return array
     */
    public function defaultAction()
    {
        $user = $this->getUser();
        $form = Form::create();
        $editableProperties = ['showNsfw', 'treeView', 'viewDeleted'];
        foreach ($editableProperties as $propertyName) {
            $property = User::proto()->getPropertyByName($propertyName);
            $property->fillForm($form);
        }

        FormHelper::booleanToTernary($form);

        $response['ok'] = true;

        if ($this->isPostRequest()) {
            $form->import($this->getRequest()->getPost());
            $form->checkRules();
            if (!$form->getErrors()) {
                FormUtils::form2object($form, $user);
                User::dao()->take($user);
            } else {
                $response['ok'] = false;
            }
        }

        $model = [];
        foreach ($editableProperties as $propertyName) {
            $property = User::proto()->getPropertyByName($propertyName);
            $model[$propertyName] = $user->{$property->getGetter()}();
        }
        $response['user'] = array_merge(
            [
                'login' => $user->getLogin(),
                'customCss' => $user->getCustomCss()
            ],
            $model
        );

        $response['form'] = FormHelper::toClient(
            $form, $user->proto()->getPropertyReadableNames(), [
                'showNsfw' => 'Показывать безнравственный и аморальный контент (расчлененка, эротика, аниме)',
                'treeView' => 'При переходе в тред, переключаться по-умолчанию на древовидный просмотр',
                'viewDeleted' => 'Показывать удаленные посты и треды в модерируемых досках'
            ]
        );

        return $response;
    }

    /**
     * @Post
     *
     * @return array
     * @throws ApiBadRequestException
     */
    public function registerAction()
    {
        $this->assertCaptcha();

        if (Cache::me()->get('disableRegister')) {
            return [
                 'ok' => false,
                'form-errors' => [
                    'invite' => 'Регистрация отключена!'
                ]
            ];   
        }

        $form = $this->makeForm();
        $form->addRule('login', CallbackLogicalObject::create(function (Form $form) {
            $login = $form->getValue('login');
            if ($login && User::dao()->getByLogin($login)) {
                $form->addCustomLabel('login', 3, 'Неправильно введён логин, введите другой');
                $form->markCustom('login', 3);
            }
            return true;
        }));

        $form
            ->import($this->getRequest()->getPost())
            ->checkRules();

        if ($form->getErrors()) {
            try {
                $invite_err = $form->getTextualErrorFor('invite');
            } catch (Exception $e) {
                $invite_err = null;
            }
            return [
                'ok' => false,
                'form-errors' => [
                    'login'     => $form->getTextualErrorFor('login'),
                    'password'  => $form->getTextualErrorFor('password'),
                    'invite'    => $invite_err
                ]
            ];

        }

        $password = $form->getValue('password');
        $login = $form->getValue('login');
        //$email = $form->getValue('email');
        try {
            $invite = $form->getValue('invite');
        } catch (Exception $e) {
            $invite = null;
        }

        if (Cache::me()->get('inviteRequired')) {
            $invite = Invite::dao()->getByInvite($invite);

            if (!$invite || $invite->isUsed()) {
                return [
                    'ok' => false,
                    'form-errors' => [
                        'invite' => 'Инвайт уже использован или его не существует'
                    ]
                ];
            }

            if (time() - $invite->getCreatedAt()->toStamp() > 86400 && !$invite->isUsed()) {
                Invite::dao()->drop($invite);
                return [
                    'ok' => false,
                    'form-errors' => [
                        'invite' => 'Инвайт истёк! (Инвайт создан более чем сутки назад)'
                    ]
                ];
            }

            $invite->setUsed(true);
        }

        $user = User::create()
            ->setCreateDate(Timestamp::makeNow())
            ->setLogin($login)
            ->setPasswordHashed($password)
            ->setRoleId(UserRole::USER);

        User::dao()->add($user);

        if ($invite) {
            $invite->setUsedBy($user);
            Invite::dao()->take($invite);
        }

        UserSession::start($this->getRequest(), $user);

        return [ 'ok' => true ];
    }

    /**
     * @Post
     *
     * @return array
     */
    public function loginAction()
    {
        $form = $this->makeForm('login');

        $form
            ->import($this->getRequest()->getPost())
            ->checkRules();

        if ($form->getErrors()) {
            return [
                'ok' => false,
                'form-errors' => [
                    'login' => $form->getTextualErrorFor('login'),
                    'password' => $form->getTextualErrorFor('password'),
                ]
            ];
        }

        $this->limitWithCaptcha('loginAttempts', 600, 3);

        $user = User::dao()->getByLogin($form->getValue('login'));

        if ($user instanceof User && $user->validatePassword($form->getValue('password'))) {
            if ($user->isDeleted()) {
                return [
                    'ok' => false,
                    'form-errors' => [
                        'invite' => 'Аккаунт удалён'
                    ]
                ];
            }
            UserSession::start($this->getRequest(), $user);
            return [ 'ok' => true ];
        } else {
            return [ 'ok' => false ];
        }
    }

    /**
     * @Post
     * @Auth
     * 
     * @return array
     */
    public function listAction()
    {
        if (!$this->getUser()->getRole()->isGlobalAdmin()) {
            throw new ApiForbiddenException();
        }

        $users = Criteria::create(User::dao())
            ->add(Expression::isFalse('deleted'))
            ->addOrder(OrderBy::create("createDate")->desc())
            ->getList();

        $response = [];

        foreach ($users as $user) {
            $response[] = $user->export();
        }

        return ['ok' => true, 'result' => $response];
    }

    /**
     * @Auth
     * 
     * @param User|null $user
     * @throws ApiForbiddenException
     * @throws Exception
     * 
     * @return array
     */
    public function removeAction(User $user = null)
    {
        if (!$this->getUser()->getRole()->isGlobalAdmin()) {
            throw new ApiForbiddenException();
        }

        if (!$user) {
            throw new ApiNotFoundException();
        }

        $user->setDeleted(true);

        User::dao()->save($user);

        return ['ok' => true];
    }

    /**
     * @Auth
     * @Post
     * 
     * @return array
     */
    public function setStyleAction()
    {
        $form = Form::create()
            ->add(
                Primitive::string('custom_css')
                    ->setMin(1)
                    ->setMax(100000)
                    ->setDefault('')
            )
            ->addWrongLabel('custom_css', "Длина стиля не должна превышать 100000 символов!")
            ->import($this->getRequest()->getPost())
            ->checkRules();

        if ($form->getErrors()) {
            return [
                'ok' => false,
                'error' => $form->getTextualErrorFor('custom_css')
            ];
        }

        $style = $form->getValue('custom_css');

        if (substr_count($style, "\n") > 10000) {
            return [
                'ok' => false,
                'error' => 'В строке не должно быть более 10000 строк!'
            ];
        }

        $user = $this->getUser()
            ->setCustomCss($style);

        User::dao()->take($user);

        return ['ok' => true];
    }

    /**
     * @return array
     */
    public function logoutAction()
    {
        $session = $this->getSession();
        if ($session instanceof UserSession) {
            $session->destroy();
        }
        return [ 'ok' => true ];
    }

    /**
     * @return Form
     */
    protected function makeForm($action='register')
    {
        $form = Form::create()
            ->add(
                Primitive::string('login')
                    ->setMin(self::LOGIN_MIN_LENGTH)
                    ->setMax(64)
                    ->required()
            )
            ->add(
                Primitive::string('password')
                    ->setMin(self::PASSWORD_MIN_LENGTH)
                    ->setMax(64)
                    ->required()
            )
            /*
            ->add(
                Primitive::string('email')
                    ->setAllowedPattern("/^[a-z0-9!%_\\+\\.\\-]+@[a-z0-9-]+(\\.[a-z0-9-]+)*$/i")
            )
            */
            ->addMissingLabel('login', 'Логин не введён')
            ->addMissingLabel('password', 'Пароль не введён')
            ->addWrongLabel('login', sprintf('Логин должен быть не короче %d знаков', self::LOGIN_MIN_LENGTH))
            ->addWrongLabel('password', sprintf('Пароль должен быть не короче %d знаков', self::PASSWORD_MIN_LENGTH))//->addWrongLabel('email',    'E-mail введён некорректно')
        ;

        if (Cache::me()->get('inviteRequired') && $action == 'register') {
            $form->add(
                Primitive::string('invite')
                    ->setMin(4)
                    ->setMax(128)
                    ->required()
            )
            ->addMissingLabel('invite', 'Для регистрации на сайте, необходим инвайт, получить его вы можете у других пользователей сайта')
            ->addWrongLabel('invite', 'Для регистрации на сайте, необходим инвайт, вы использовали неверный инвайт');
        }

        return $form;
    }

    /**
     * @Auth
     * @Post
     *
     * @return array
     */
    public function changePasswordAction()
    {
        $form = Form::create()
            ->add(Primitive::string('oldPassword')->required())
            ->add(Primitive::string('newPassword')->setMin(8)->required())
            ->addMissingLabel('oldPassword', 'Не введён старый пароль')
            ->addMissingLabel('newPassword', 'Не введён новый пароль')
            ->addWrongLabel('oldPassword', 'Пароль не подходит')
            ->addWrongLabel('newPassword', sprintf('Пароль должен быть не короче %d знаков', self::PASSWORD_MIN_LENGTH))
            ->addRule('oldPassword', CallbackLogicalObject::create(function (Form $form) {
                $oldPassword = $form->getValue('oldPassword');
                return !$oldPassword || $this->getUser()->validatePassword($oldPassword);
            }))
            ->import($this->getRequest()->getPost());

        if ($form->checkRules()->getErrors()) {
            return [
                'ok' => false,
                'form-errors' => [
                    'oldPassword' => $form->getTextualErrorFor('oldPassword'),
                    'newPassword' => $form->getTextualErrorFor('newPassword'),
                ]
            ];
        }

        $this->getUser()->setPasswordHashed($form->getValue('newPassword'));
        User::dao()->save($this->getUser());

        $this->getSession()->destroy();
        UserSession::start($this->getRequest(), $this->getUser());

        return [ 'ok' => true ];
    }
}