<?php
/**
 * test for BlogHelper
 *
 * baserCMS :  Based Website Development Project <http://basercms.net>
 * Copyright (c) baserCMS Users Community <http://basercms.net/community/>
 *
 * @copyright		Copyright (c) baserCMS Users Community
 * @link      http://basercms.net baserCMS Project
 * @package     Baser.Test.Case.View.Helper
 * @since        baserCMS v 3.0.6
 * @license     http://basercms.net/license/index.html
 */

App::uses('BcBaserHelper', 'View/Helper');
App::uses('BcAppView', 'View');

/**
 * Blog helper library.
 *
 * @package Baser.Test.Case
 * @property \BlogHelper $Blog
 * @property \BlogPost $BlogPost
 * @property \BlogContent $BlogContent
 * @property \BcBaserHelper $BcBaser
 * @property BcAppView $View
 */
class BlogBaserHelperTest extends BaserTestCase {

/**
 * Fixtures
 * @var array 
 */
	public $fixtures = [
		'plugin.blog.View/Helper/BlogBaserHelper/ContentMultiBlog',	// テスト内で読み込む
		'baser.Default.Content', 
		'baser.Default.Site',
		'baser.Default.SiteConfig',
		'baser.Default.User',
		'baser.Default.UserGroup',
		'baser.Default.BlogContent',
		'baser.Default.BlogTag',
		'baser.Default.BlogComment',
		'baser.Default.BlogPostsBlogTag',
		'plugin.blog.Model/BlogPost/BlogCategoryModel',
		'plugin.blog.View/Helper/BlogBaserHelper/BlogPostBlogBaserHelper',
	];
	
/**
 * setUp
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$this->View = new BcAppView();
		$this->BcBaser = new BcBaserHelper($this->View);
		$this->BlogBaser = $this->BcBaser->getPluginBaser('Blog');
	}

/**
 * tearDown
 *
 * @return void
 */
	public function tearDown() {
		unset($this->BcBaser);
		Router::reload();
		parent::tearDown();
	}

/**
 * ブログ記事一覧出力
 *
 * @param string | array $contentsName 管理システムで指定したコンテンツ名
 * @param int $num 記事件数
 * @param array $options オプション
 * @param expected string 期待値
 * @param message string テスト失敗時に表示されるメッセージ
 */
	public function testBlogPosts() {
		$this->markTestIncomplete('このメソッドは、BlogHelper::posts() をラッピングしているメソッドの為スキップします。');
	}

/**
 * 全ブログコンテンツの基本情報を取得する
 *
 * @return void
 */
	public function testGetBlogs() {
		$this->markTestIncomplete('このメソッドは、BlogHelper::getContents() をラッピングしているメソッドの為スキップします。');
	}

/**
 * 現在のページがブログプラグインかどうかを判定する
 *
 * @param bool $expected 期待値
 * @param string $url リクエストURL
 * @return void
 * @dataProvider isBlogDataProvider
 */
	public function testIsBlog($expected, $url) {
		$BlogBaser = $this->BcBaser->getPluginBaser('Blog');
		$BlogBaser->request = $this->_getRequest($url);
		$this->assertEquals($expected, $this->BcBaser->isBlog());
	}

	public function isBlogDataProvider() {
		return [
			//PC
			[false, '/'],
			[false, '/index'],
			[false, '/contact/index'],
			[true, '/news/index'],
			// モバイルページ
			[false, '/m/'],
			[false, '/m/index'],
			[false, '/m/contact/index'],
			[true, '/m/news/index'],
			// スマートフォンページ
			[false, '/s/'],
			[false, '/s/index'],
			[false, '/s/contact/index'],
			[true, '/s/news/index']
		];
	}

/**
 * ブログのカテゴリ取得
 * 
 * BlogHelper::getCategories() のラッピングの為、呼び出せるかどうかだけテストし、
 * 詳細なテストは、BlogHelper::getCategories() に委ねる
 */
	public function testGetBlogCategories() {
		$categories = $this->BcBaser->getBlogCategories();
		$this->assertEquals(2, count($categories));
	}

/**
 * ブログの子カテゴリを持っているかどうか
 *
 * BlogHelper::hasChildCategory() のラッピングの為、テストはスルー
 */
	public function testHasChildBlogCategory() {
		$this->markTestIncomplete('このメソッドは、BlogHelper::hasChildCategory() をラッピングしているメソッドの為スキップします。');
	}

/**
 * ブログタグリストを取得する
 */
	public function testGetBlogTagList() {
		$this->markTestIncomplete('このメソッドは、BlogHelper::getBlogTagList() をラッピングしているメソッドの為スキップします。');
	}

/**
 * ブログタグリストを取得する
 */
	public function testBlogTagList() {
		$this->markTestIncomplete('このメソッドは、BlogHelper::BlogTagList() をラッピングしているメソッドの為スキップします。');
	}

}