<?php

class ApiThreadController extends ApiBaseController
{
    /**
     * @param Thread|null $thread
     * @param Post|null $post
     * @return array
     * @throws ApiNotFoundException
     */
    public function defaultAction(Thread $thread = null, Post $post = null, $after = null)
    {
        if ($post instanceof Post) {
            $thread = $post->getThread();
        }
        if (!$thread) {
            throw new ApiNotFoundException();
        }
        if ($thread->getBoard()->isDeleted()) {
            throw new ApiNotFoundException();
        }

        $canModerateBoard = $this->getUser() && $this->getUser()->canModerateBoard($thread->getBoard());

        if ($this->getSession()->isIpCountryRu() && $thread->getBoard()->isBlockRu() && !$canModerateBoard) {
            throw new ApiBlockRuException();
        }

        if ($this->getUser() && $this->getUser()->canModerateBoard($thread->getBoard())) {
            $canViewDeleted = $this->getUser()->isViewDeleted();
        } else {
            $canViewDeleted = false;
        }

        if ($thread->isDeleted() && !$canViewDeleted) {
            throw new ApiNotFoundException();
        }

        $criteria = Criteria::create(Post::dao())
            ->fetchCollection('referencedBys')
            ->fetchCollection('referencesTos')
            ->fetchCollection('replies')
            ->fetchCollection('attachments')
            ->add(Expression::eq('thread', $thread))
            ->addOrder(OrderBy::create('createDate')->asc());

        if ($after) {
            $criteria->add(Expression::gt('id', $after));
        }

        if (!$canViewDeleted) {
            $criteria->add(Expression::isFalse('deleted'));
        }

        /** @var Post[] $posts */
        $posts = $criteria->getList();

        $response = [
            'board' => $thread->getBoard()->exportExtended($this->getSession()),
            'thread' => $thread->export(),
            'posts' => []
        ];

        foreach ($posts as $post) {
            $response['posts'] [] = $post->export();
        }

        return $response;
    }

    /**
     * @Post
     *
     * @param Board $board
     * @return array
     */
    public function createAction(Board $board)
    {
        $this->limitWithCaptcha('newThread_hour', 3600, $this->getUser() ? 3 : 0);
        $this->limitWithCaptcha('newThread_min',    60, $this->getUser() ? 1 : 0);

        $thread = Thread::create()
            ->setBoard($board)
            ->setCreateDate(Timestamp::makeNow())
            ->setUpdateDate(Timestamp::makeNow());

        return $this->addPost($thread);
    }

    /**
     * @Post
     *
     * @param Post $parent
     * @return array
     */
    public function replyAction(Post $parent)
    {
        $this->limitWithCaptcha('replyTo_hour', 3600, $this->getUser() ? 12 : 0);
        $this->limitWithCaptcha('replyTo_min',    60, $this->getUser() ?  3 : 0);

        if ($parent->isDeleted()) {
            return ['ok' => false, 'reason' => 'deleted'];
        }

        $thread = $parent->getThread();

        if ($thread->isLocked()) {
            return ['ok' => false, 'reason' => 'locked'];
        }

        return $this->addPost($thread, $parent);
    }

    /**
     * @param Thread $thread
     * @param Post|null $parent
     * @return array
     * @throws Exception
     */
    protected function addPost(Thread $thread, Post $parent = null)
    {
        if ($parent instanceof Post) {
            Assert::isEqual($thread->getId(), $parent->getThreadId(), 'parent/thread mismatch');
        }

        $ban = $this->getSession()->getActiveBanInBoard($thread->getBoard());
        if ($ban) {
            return [
                'ok' => false,
                'reason' => 'ban',
                'ban' => $ban->export()
            ];
        }

        if ($this->getSession()->isIpCountryRu() && $thread->getBoard()->isBlockRu()) {
            return [
                'ok' => false,
                'reason' => 'blockRu',
            ];
        }

        if (Cache::me()->get('registerRequired') && !$this->getUser()) {
            return [
                'ok' => false,
                'reason' => 'registerRequired'
            ];
        }

        if (!RequestUtils::getRealIpHash($this->getRequest())) {

            if (Cache::me()->get('disableTorPosting')) {
                return [
                    'ok' => false,
                    'reason' => 'tor_disabled'
                ];
            }

            if (Cache::me()->get('torgateRegisterRequired') && !$this->getUser()) {
                return [
                    'ok' => false, 
                    'reason' => 'registerRequired'
                ];
            }
        }

        $form = Form::create()
            ->add(
                FormHelper::stringPrimitive(Post::proto(), 'message')
            )
            ->add(
                Primitive::set('images')
                    ->setMax(8)
            )
            ->add(
                Primitive::string('identity')
                    ->setMax(64)
                    ->setdefault('notselected')
            )
            ->add(
                Primitive::ternary('sage')
                    ->setDefault(false)
            )
            ->addWrongLabel('message', 'Слишком длинное сообщение')
            ->addWrongLabel('images', 'Сликом много файлов приложено');

        $form->import($this->getRequest()->getPost());

        if ($form->getErrors()) {
            return [
                'ok' => false,
                'reason' => 'form',
                'errors' => array_values($form->getTextualErrors())
            ];
        }

        $message = $form->getValue('message');
        $sage = $form->getValue('sage');

        $isSageAllowed = $thread->getBoard()->getSage();
        $isIdentityAllowed = $thread->getBoard()->getIdentity();

        $post = Post::create()
            ->setCreateDate(Timestamp::makeNow())
            ->setMessage($message);
        
        if ($sage && $isSageAllowed) {
            $post->setSage($sage);
        }

        if ($this->getSession()->getIpHash()) {
            $post->setIpHash($this->getSession()->getIpHash());
        }
        if ($this->getUser()) {
            $post->setUser($this->getUser());
        }

        if ($parent instanceof Post) {
            $post->setParent($parent);
        }

        $isTimeoutEnabled = Cache::me()->get('globalTimeout');
        $timeout_cmd = null;
        $af_key = Cache::me()->get('af:'.$thread->getBoard()->getId());

        $isReplyTimeoutEnabled = Cache::me()->get('repliesLimit');
        $repliesLimitMax = intval(Cache::me()->get('repliesLimitMax'));

        if (!$repliesLimitMax) {
            $repliesLimitMax = 100;
        }

        $limit_key = Cache::me()->get('repliesLimitCounter');

        $repliesLimitCmd = null;

        if ($isIdentityAllowed && $form->getValue('identity') && $form->getValue('identity') != 'notselected' && $this->getUser()) {
            $identity = Criteria::create(UserIdentity::dao())
                ->add(Expression::eq('address', $form->getValue('identity')))
                ->add(Expression::eq('user', $this->getUser()))
                ->get();

            if (!$identity) {
                return [
                    'ok' => false,
                    'reason' => "wrong_identify"
                ];
            }

            $post->setIdentity($identity);
        }

        $db = DBPool::getByDao(Post::dao());
        try {
            $db->begin();

            if (!$thread->getId()) {
                Thread::dao()->add($thread);
            }

            $bumpLimit = Criteria::create(Post::dao())
                    ->add(Expression::eq('thread', $thread))
                    ->add(Expression::isFalse('deleted'))
                    ->addProjection(Projection::count('id', 'count'))
                    ->getCustom('count') >= $thread->getBoard()->getBumpLimit();

            if ($bumpLimit && !$thread->isBumpLimitReached()) {
                $thread->setBumpLimitReached(true);
                Thread::dao()->take($thread);
            } else if (!$bumpLimit) {
                $thread->setBumpLimitReached(false);
                if ($sage && $isSageAllowed) {
                    /* do nothing */
                } else {
                    $thread->setUpdateDate($post->getCreateDate());
                }
                Thread::dao()->take($thread);
            }

            $post->setThread($thread);
            Post::dao()->add($post);

            $uploadedImageTokens = $form->getValue('images');
            $imageIds = [];
            if (count($uploadedImageTokens) > 0) {
                $imageIds = Attachment::dao()->useInPost($post, $uploadedImageTokens);
            }

            if (empty($message) && empty($imageIds)) {
                throw new ApiBadRequestException('no message and no files to post');
            }

            if ($thread->getBoard()->getImrequired()) {
                if($parent == null && empty($imageIds)){
                    return ["ok" => false, "reason" => "gimme_image"];
                }
            }

            if ($isTimeoutEnabled) {
                if ($parent == null) {
                    if ($af_key && $af_key >= 5) {
                        return ['ok' => false, "reason" => 'global_timeout'];
                    }

                    if (!$af_key) {
                        $timeout_cmd = 'create';
                    } else {
                        $timeout_cmd = 'increment';
                    }
                }
            }

            if ($isReplyTimeoutEnabled) {
                if ($limit_key && $limit_key >= $repliesLimitMax) {
                    return ['ok' => false, "reason" => 'replies_timeout'];
                }

                if (!$limit_key) {
                    $repliesLimitCmd = 'create';
                } else {
                    $repliesLimitCmd = 'increment';
                }
            }

            if ($thread->getBoard()->getTextboard()) {
                if(!empty($imageIds)){
                    return ["ok" => false, "reason" => "textboard"];
                }  
            }

            if (preg_match_all('/>>([0-9]+)/', $message, $refMatches)) {
                $refPostIds = array_unique($refMatches[1]);
                $refPosts = Post::dao()->getListByIds($refPostIds);
                foreach ($refPosts as $refPost) {
                    PostReference::dao()->add(
                        PostReference::create()
                            ->setReferencedBy($post)
                            ->setReferencesTo($refPost)
                    );
                }
            }

            $db->commit();

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            throw $e;
        }

        $thread->getPostCount(true);

        if ($timeout_cmd) { // Этот костыль здесь потому что Cache не работает во время щапущенной транзакции... миша гей.
            switch ($timeout_cmd) {
                case 'create':
                    Cache::me()->increment('af:'.$thread->getBoard()->getId(), 1);
                    Cache::me()->expire('af:'.$thread->getBoard()->getId(), 3600);
                    break;

                case 'increment':
                    Cache::me()->increment('af:'.$thread->getBoard()->getId(), 1);
                    break;
                
                default:
                    break;
            }
        }

        if ($repliesLimitCmd) {
            switch ($repliesLimitCmd) {
                case 'create':
                    Cache::me()->increment('repliesLimitCounter', 1);
                    Cache::me()->expire('repliesLimitCounter', 3600);
                    break;

                case 'increment':
                    Cache::me()->increment('repliesLimitCounter', 1);
                    break;
                
                default:
                    break;
            }
        }

        return [
            'ok' => true,
            'post' => $post->export()
        ];
    }

    /**
     * @Auth
     *
     * @param Thread $thread
     * @param boolean $isWatched
     * @return array
     */
    public function watchAction(Thread $thread, $isWatched)
    {
        $isWatched = $this->getBooleanParam($isWatched);
        $watchedThreadIds = $this->getUser()->getWatchedThreads(true)->getList();
        $updated = false;
        if ($isWatched && !in_array($thread->getId(), $watchedThreadIds)) {
            $watchedThreadIds [] = $thread->getId();
            $updated = true;
        } else if (!$isWatched && in_array($thread->getId(), $watchedThreadIds)) {
            $watchedThreadIds = array_filter(
                $watchedThreadIds,
                function ($id) use ($thread) {
                    return $id != $thread->getId();
                }
            );
            $updated = true;
        }

        if ($updated) {
            $this->getUser()->getWatchedThreads(true)
                ->setList($watchedThreadIds)
                ->save();
        }

        return [
            'ok' => true,
            'thread' => $thread->getId(),
            'isWatched' => $isWatched
        ];
    }
}