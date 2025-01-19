<?php

class ApiPostController extends ApiBaseController
{
    /**
     * @param Post $post
     * @return array
     * @throws ApiBlockRuException
     */
    public function defaultAction(Post $post)
    {
        if ($this->getSession()->isIpCountryRu() && $post->getThread()->getBoard()->isBlockRu() && !$post->canBeModeratedBy($this->getUser())) {
            throw new ApiBlockRuException();
        }

        return ['post' => $post->export()];
    }

    /**
     * @Auth
     *
     * @param Post $post
     * @param $isLike
     * @return array
     */
    public function rateAction(Post $post, $isLike)
    {

        if ($isLike == null) {
            $isLike = false;
        } else {
            $isLike = true;
        }

        if (!$post->getThread()->getBoard()->isLikes()) {
            return [
                'ok' => false,
                'error' => 'likes_disabled'
            ];
        }

        if ($post->getUser()) {
            if ($this->getUser()->getLogin() == $post->getUser()->getLogin()) {
                return [
                    'ok' => false,
                    'error' => 'op_match'
                ];
            }
        }

        $check = Criteria::create(Rate::dao())
            ->add(Expression::eq('user', $this->getUser()))
            ->add(Expression::eq('post', $post))
            ->get();

        if ($check) {
            $res = [
                'ok' => false,
                'error' => 'aleardy_voted',
                'isLike' => $check->isLiked()
            ];

            if ($isLike == $check->isLiked()) {
                Rate::dao()->drop($check);

                $res['cancel'] = true;
            } else {
                $check->setLiked($isLike);

                Rate::dao()->take($check);
            }

            return $res;
        }

        $rate = Rate::create()
            ->setPost($post)
            ->setUser($this->getUser())
            ->setLiked($isLike);

        Rate::dao()->take($rate);

        return ['ok' => true];
    }
}