<?php

class ApiGlobalsController extends ApiBaseController
{
    private $keys = [
        'registerRequired' => ['title' => 'Постинг после регистрации', 'description' => 'Постинг возможен только после регистрации.'],
        'inviteRequired' => ['title' => 'Инвайты', 'description' => 'Регистрация возможна только при наличии инвайта.'],
        'torgateRegisterRequired' => ['title' => 'Постинг после регистрации (Tor)', 'description' => 'Постинг через Tor-зеркало возможно только после регистрации'],
        'disableTorPosting' => ['title' => 'Постинг через Tor', 'description' => 'Отключить постинг для пользователей тора'],
        'globalTimeout' => ['title' => 'Таймаут', 'description' => 'Таймаут на создание тредов 3 треда за 5 минут после чего ожидание час.'],
        //'dupeimageLimit' => ['title' => 'Лимит на одинаковые изображения', 'description' => 'Фильтр одинаковых изображений (не более 1-го одинакового изображения в N минут'],
        'repliesLimit' => ['title' => 'Ограничение постинга', 'description' => 'Ограничение постинга N постов в час'],
        'repliesLimitMax' => ['title' => 'Ограничение постинга', 'description' => 'Макс кол-во постов в час', 'type' => 'int', 'default' => 100],
        'disableRegister' => ['title' => 'Отключает регистрацию', 'description' => 'Отключает полностью регистрацию']
    ];

    /**
     * @throws ApiForbiddenException
     */
    protected function assertAccess()
    {
        if (!$this->getUser()->getRole()->isGlobalAdmin()) {
            throw new ApiForbiddenException;
        }
    }

    /**
     * @Auth
     * @return array
     * @throws ApiForbiddenException
     */
    public function listAction()
    {
        $this->assertAccess();

        /** @var User[] $globals */
        $globals = Criteria::create(User::dao())
            ->add(Expression::gt('role', UserRole::USER))
            ->addOrder(OrderBy::create('role')->desc())
            ->addOrder(OrderBy::create('login')->asc())
            ->getList();

        $response = [
            'globals' => []
        ];
        foreach ($globals as $global) {
            $response['globals'] []= [
                'login' => $global->getLogin(),
                'role' => $global->getRole()->getName()
            ];
        }

        return $response;
    }

    /**
     * @Auth
     * @param User $user
     * @param bool $isAdmin
     * @return array
     * @throws ApiForbiddenException
     */
    public function addAction(User $user, bool $isAdmin = false)
    {
        $this->assertAccess();

        if ($isAdmin) {
            $role = UserRole::ADMIN;
        } else {
            $role = UserRole::MODERATOR;
        }

        $user->setRoleId($role);
        User::dao()->save($user);

        return [ 'ok' => true ];
    }

    /**
     * @param User $user
     * @return array
     * @throws ApiForbiddenException
     */
    public function removeAction(User $user)
    {
        $this->assertAccess();

        $user->setRoleId(UserRole::USER);
        User::dao()->save($user);

        return [ 'ok' => true ];
    }

    /**
     * @Auth
     * @return array
     * @throws ApiForbiddenException
     */
    public function settingsAction()
    {
        $this->assertAccess();

        $cache = Cache::me();

        $res = [];

        $form = [];

        foreach (array_keys($this->keys) as $key) {
            $res[$key] = $cache->get($key);
            $type = 'bool';
            $default = false;

            if (isset($this->keys[$key]['type'])) {
                $type = $this->keys[$key]['type'];
            }

            if (isset($this->keys[$key]['default'])) {
                $default = $this->keys[$key]['default'];
            }

            $form[] = ['name' => $key, 'required' => true, 'type' => $type, 'title' => $this->keys[$key]['title'], 'description' => $this->keys[$key]['description'], 'default' => $default]; // TODO: Перепилить этот костыль на нативное использование форм.
        }

        return ['ok' => true, 'result' => $res, 'form' => $form];
    }

    /**
     * @Auth
     * @Post
     * @return array
     * @throws ApiForbiddenException
     */
    public function settingsUpdateAction()
    {
        $this->assertAccess();

        $post_data = $this->getRequest()->getPost();

        foreach(array_keys($this->keys) as $key) {
            if (!isset($post_data[$key])) {
                return ['ok' => false, 'errors' => ['Не все поля заполнены']];
            }
        }

        $cache = Cache::me();

        foreach(array_keys($this->keys) as $key) {
            $value = $post_data[$key];

            if ($value) {
                $cache->set($key, $value, time());
            } else {
                $cache->delete($key);
            }
        }

        return ['ok' => true];
    }

}
