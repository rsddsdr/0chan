<?php

class ApiInviteController extends ApiBaseController
{
	/**
	 * @Auth
	 * return array
	 */
	public function defaultAction()
	{
		$criteria = Criteria::create(Invite::dao())
			->add(Expression::eq('user', $this->getUser()))
			->addOrder(OrderBy::create('createdAt')->desc());

		$invites = $criteria->getList();

		$response = [
			'ok' => true,
			'invites' => []
		];

		$now = time();

		foreach ($invites as $invite) 
		{
			$inv = $invite->export();

			if ($now - $invite->getCreatedAt()->toStamp() > 86400 && !$invite->isUsed()) {
				$inv['expired'] = true;
				Invite::dao()->drop($invite);
			}

			$response['invites'][] = $inv;
		}

		return $response;
	}

	/**
	 * @Auth
	 * return array
	 */
	public function createAction()
	{
		$criteria = Criteria::create(Invite::dao())
			->add(Expression::eq('user', $this->getUser()))
			->addOrder(OrderBy::create('createdAt')->desc());

		if (sizeof($criteria->getList()) >= 3) {
			return ['ok' => false, 'result' => 'limit_exceeded'];
		}

		$invite = Invite::create()
			->setUser($this->getUser())
			->setCreatedAt(Timestamp::makeNow())
			->setInvite(uniqid());

		Invite::dao()->add($invite);

		return ['ok' => true, 'result' => $invite->export()];
	}
}