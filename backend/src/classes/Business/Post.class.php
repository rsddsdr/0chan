<?php
/*****************************************************************************
 *   Copyright (C) 2006-2009, onPHP's MetaConfiguration Builder.             *
 *   Generated by onPHP-1.1.master at 2014-04-16 15:41:40                    *
 *   This file will never be generated again - feel free to edit.            *
 *****************************************************************************/

	class Post extends AutoPost implements Prototyped, DAOConnected
	{
		/**
		 * @return Post
		**/
		public static function create()
		{
			return new self;
		}
		
		/**
		 * @return PostDAO
		**/
		public static function dao()
		{
			return Singleton::getInstance('PostDAO');
		}
		
		/**
		 * @return ProtoPost
		**/
		public static function proto()
		{
			return Singleton::getInstance('ProtoPost');
		}

		public function getMessageHtml() {
            if (!APCU_ENABLED) {
                return Markdown::format($this->getMessage());
            }

            $htmlCacheKey = __CLASS__ . ':html:' . $this->getId();
            $html = apcu_fetch($htmlCacheKey);
            if (!$html) {
                $html = Markdown::format($this->getMessage());
                apcu_store($htmlCacheKey, $html, 60);
            }

            return $html;
		}

		public function export() {
            $session = App::me()->getSession();
            $viewer = $session->getUser();
            $canBeModerated = $viewer && $this->canBeModeratedBy($viewer);

            $hideMessage = false;
            if ($this->isDeleted() && !$canBeModerated) {
                $hideMessage = true;
            }

            $activeBan = $session->getActiveBanInBoard($this->getThread()->getBoard());

            $canReply = true;
            if ($this->getThread()->isLocked() && !($viewer && $this->canBeModeratedBy($viewer))) {
                $canReply = false;
            }
            if ($this->getThread()->isDeleted() || $this->getThread()->getBoard()->isDeleted()) {
                $canReply = false;
            }
            if ($activeBan) {
                $canReply = false;
            }

			$res =  [
				'id'                => $this->getId(),
				'boardDir'          => $this->getThread()->getBoard()->getDir(),
				'threadId'          => $this->getThreadId(),
				'opPostId'          => $this->getThread()->getOpPost()->getId(),
				'parentId'          => $this->getParentId(),
				'date'              => $this->getCreateDate()->toStamp(),
                'isOpPost'          => $this->isOpPost(),
                'message'           => $hideMessage ? '[удалено]' : $this->getMessage(),
				'messageHtml'       => $hideMessage ? '[удалено]' : $this->getMessageHtml(),
                'attachments'       => $hideMessage ? [] : $this->exportAttachments($canBeModerated),
                'referencedByIds'   => $this->getReferencedByIds(),
                'referencesToIds'   => $this->getReferencesToIds(),
                'repliedByIds'      => $this->isOpPost() ? [] : $this->getRepliedByIds(),
                'isDeleted'         => $this->isDeleted(),
                'isUserBanned'      => $this->isBanned(),
                'canBeModerated'    => $canBeModerated,
                'canBeReported'     => $viewer != null || $session->getIpHash() != null,
                'canReplyTo'        => $canReply,
                'ban'               => $activeBan ? $activeBan->getId() : null,
                'sage'              => $this->isSage()
			];

            if ($this->getIdentityId()) {
                $res['identity'] = $this->getIdentity()->export();
            }

            if ($this->getThread()->getBoard()->isLikes()) {
                $rates = Criteria::create(Rate::dao())
                    ->add(Expression::eq('post', $this));

                $rates = $rates->getList();

                $res['likes'] = ['liked' => 0, 'disliked' => 0];

                foreach ($rates as $rate)
                {
                    if ($rate->isLiked()) {
                        $res['likes']['liked']++;
                    } else {
                        $res['likes']['disliked']++;
                    }
                }
            }

            if ($viewer && $viewer->canManageAllBoards() && $this->getUser()) {
                $usr = $this->getUser();
                $res['user'] = ['name' => mb_strimwidth($usr->getLogin(), 0, 64, "..."), 'id' => $usr->getId()];
            }

            return $res;
		}

        public function exportAttachments($withDeleted) {
            $export = [];
            /** @var Attachment[] $attachments */
            $attachments = $this->getAttachments()->getList();

            foreach ($attachments as $attachment) {
                if ($attachment->isDeleted() && !$withDeleted) {
                    continue;
                }
                $export []= $attachment->export();
            }
            return $export;
        }

        public function canBeModeratedBy(User $user = null)
        {
            if (!$user) {
                return null;
            }
            return $user->canModerateBoard($this->getThread()->getBoard());
        }

        public function isOpPost()
        {
            return $this->getParentId() == null;
        }

        public function getReferencedByIds()
        {
            /** @var PostReference[] $refs */
            $refs = $this->getReferencedBys()->getList();
            $refPostIds = [];
            foreach ($refs as $ref) {
                $refPostIds []= (int)$ref->getReferencedById();
            }
            return $refPostIds;
        }

        public function getReferencesToIds()
        {
            /** @var PostReference[] $refs */
            $refs = $this->getReferencesTos()->getList();
            $refPostIds = [];
            foreach ($refs as $ref) {
                $refPostIds []= (int)$ref->getReferencesToId();
            }
            return $refPostIds;
        }

        public function getRepliedByIds()
        {
            $ids = [];
            /** @var Post[] $replies */
            $replies = $this->getReplies()->getList();
            foreach ($replies as $reply) {
                if (!$reply->isDeleted()) {
                    $ids []= (int)$reply->getId();
                }
            }
            return $ids;
        }

	}
?>