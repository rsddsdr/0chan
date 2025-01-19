<?php
require '../config.inc.php';

$admin = User::dao()->getByLogin('admin');
if (!$admin) {
    $admin = User::create()
        ->setCreateDate(Timestamp::makeNow())
        ->setLogin('__ADMIN_LOGIN__')
        ->setPasswordHashed('__ADMIN_PASSWD__')
        ->setRoleId(UserRole::ADMIN);

    User::dao()->add($admin);

    echo "created admin!\n";
}
