<?php
/**
 * baserCMS :  Based Website Development Project <https://basercms.net>
 * Copyright (c) NPO baser foundation <https://baserfoundation.org/>
 *
 * @copyright     Copyright (c) NPO baser foundation
 * @link          https://basercms.net baserCMS Project
 * @since         5.0.0
 * @license       https://basercms.net/license/index.html MIT License
 */

namespace BcBlog\Service;

use Cake\Datasource\EntityInterface;

/**
 * BlogContentsServiceInterface
 */
interface BlogContentsServiceInterface
{

    /**
     * 単一データ取得
     * @param int $id
     * @return \Cake\Datasource\EntityInterface|array|null
     * @checked
     * @noTodo
     * @unitTest
     */
    public function get(int $id);

    /**
     * 初期値を取得する
     * @return EntityInterface
     * @checked
     * @noTodo
     * @unitTest
     */
    public function getNew();

    /**
     * 更新
     * @param EntityInterface $target
     * @param array $postData
     * @return EntityInterface
     * @checked
     * @noTodo
     * @unitTest
     */
    public function update(EntityInterface $target, array $postData);

    /**
     * ブログ登録
     * @param array $data
     * @param array $options
     * @return \Cake\Datasource\EntityInterface
     * @throws \Cake\ORM\Exception\PersistenceFailedException
     * @checked
     * @noTodo
     * @unitTest
     */
    public function create(array $postData, $options = []): ?EntityInterface;

    /**
     * ブログをコピーする
     *
     * @param array $postData
     * @return EntityInterface $result
     * @checked
     * @unitTest
     * @noTodo
     * @unitTest
     */
    public function copy($postData);

    /**
     * ブログを削除する
     * @param int $id
     * @return bool
     * @checked
     * @noTodo
     * @unitTest
     */
    public function delete(int $id): bool;

}