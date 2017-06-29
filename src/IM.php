<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-06-11
 */

namespace fk\ease\mob;

/**
 * Class IM
 * @package fk\ease\mob
 *
 * for all methods below, it returns an array
 *  [
 *      int $responseStatusCode,
 *      array $responseData
 *  ]
 *
 * @method array userRegister(string $username, string $password, string $nickname)
 * @method array userModifyNickname(string $username, string $nickname)
 * @method array addFriend(string $myUsername, string $friendUsername)
 * @method array removeFriend(string $myUsername, string $friendUsername)
 * @method array logout(string $username)
 *
 */
class IM extends IMBase
{

    public $host = 'https://a1.easemob.com';
    public $appKey = 's1314520#lele';
    public $clientID = 'YXA6dN4a8EEOEeekEBHZZPhQ5A';
    public $clientSecret = 'YXA6UNj4gq-gnP857gCJwe6ahFCFzxs';

    public $orgName;
    public $appName;

    protected function prepareUserRegister(string $username, string $password, string $nickname)
    {
        return [
            'post',
            API::USERS,
            compact('username', 'password', 'nickname')
        ];
    }

    protected function prepareUserModifyNickname(string $username, $nickname)
    {
        return [
            'put',
            "users/$username",
            compact('nickname')
        ];
    }

    protected function prepareAddFriend(string $myUsername, string $friendsUsername)
    {
        return [
            'post',
            "users/$myUsername/contacts/users/$friendsUsername"
        ];
    }

    protected function prepareRemoveFriend(string $myUsername, string $friendUsername)
    {
        return [
            'delete',
            "users/$myUsername/contacts/users/$friendUsername"
        ];
    }

    protected function prepareLogout(string $username)
    {
        return [
            'get',
            "/users/{$username}/disconnect"
        ];
    }

}