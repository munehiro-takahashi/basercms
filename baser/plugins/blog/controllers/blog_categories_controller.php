<?php
/* SVN FILE: $Id$ */
/**
 * カテゴリコントローラー
 *
 * PHP versions 5
 *
 * baserCMS :  Based Website Development Project <http://basercms.net>
 * Copyright 2008 - 2012, baserCMS Users Community <http://sites.google.com/site/baserusers/>
 *
 * @copyright		Copyright 2008 - 2012, baserCMS Users Community
 * @link			http://basercms.net baserCMS Project
 * @package			baser.plugins.blog.controllers
 * @since			baserCMS v 0.1.0
 * @version			$Revision$
 * @modifiedby		$LastChangedBy$
 * @lastmodified	$Date$
 * @license			http://basercms.net/license/index.html
 */
/**
 * Include files
 */
/**
 * カテゴリコントローラー
 *
 * @package baser.plugins.blog.controllers
 */
class BlogCategoriesController extends BlogAppController {
/**
 * クラス名
 *
 * @var string
 * @access public
 */
	var $name = 'BlogCategories';
/**
 * モデル
 *
 * @var array
 * @access public
 */
	var $uses = array('Blog.BlogCategory','Blog.BlogContent');
/**
 * ヘルパー
 *
 * @var array
 * @access public
 */
	var $helpers = array('BcText','BcTime','BcForm','Blog.Blog');
/**
 * コンポーネント
 *
 * @var array
 * @access public
 */
	var $components = array('AuthEx','Cookie','AuthConfigure');
/**
 * ぱんくずナビ
 *
 * @var string
 * @access public
 */
	var $crumbs = array(
		array('name' => 'プラグイン管理', 'url' => array('plugin' => '', 'controller' => 'plugins', 'action' => 'index')),
		array('name' => 'ブログ管理', 'url' => array('controller' => 'blog_contents', 'action' => 'index'))
	);
/**
 * サブメニューエレメント
 *
 * @var array
 * @access public
 */
	var $subMenuElements = array();
/**
 * beforeFilter
 *
 * @return void
 * @access public
 */
	function beforeFilter() {
		
		parent::beforeFilter();
		
		$this->BlogContent->recursive = -1;
		$this->blogContent = $this->BlogContent->read(null,$this->params['pass'][0]);
		$this->crumbs[] = array('name' => $this->blogContent['BlogContent']['title'].'管理', 'url' => array('controller' => 'blog_posts', 'action' => 'index', $this->params['pass'][0]));
		
		if($this->params['prefix'] == 'admin') {
			$this->subMenuElements = array('blog_posts', 'blog_categories', 'blog_common');
		}
		
		// バリデーション設定
		$this->BlogCategory->validationParams['blogContentId'] = $this->blogContent['BlogContent']['id'];
		
	}
/**
 * beforeRender
 *
 * @return void
 * @access public
 */
	function beforeRender() {
		
		parent::beforeRender();
		$this->set('blogContent',$this->blogContent);
		
	}
/**
 * [ADMIN] ブログを一覧表示する
 *
 * @return void
 * @access public
 */
	function admin_index($blogContentId) {

		$conditions = array('BlogCategory.blog_content_id'=>$blogContentId);
		$_dbDatas = $this->BlogCategory->generatetreelist($conditions);
		$dbDatas = array();
		foreach($_dbDatas as $key => $dbData) {
			$category = $this->BlogCategory->find(array('BlogCategory.id'=>$key));
			if(preg_match("/^([_]+)/i",$dbData,$matches)) {
				$prefix = str_replace('_','&nbsp&nbsp&nbsp',$matches[1]);
				$category['BlogCategory']['title'] = $prefix.'└'.$category['BlogCategory']['title'];
			}
			$dbDatas[] = $category;
		}

		/* 表示設定 */
		$this->set('owners', $this->BlogCategory->getControlSource('owner_id'));
		$this->set('dbDatas',$dbDatas);
		$this->pageTitle = '['.$this->blogContent['BlogContent']['title'].'] ブログカテゴリ一覧';
		$this->help = 'blog_categories_index';

	}
/**
 * [ADMIN] 登録処理
 *
 * @param string $blogContentId
 * @return void
 * @access public
 */
	function admin_add($blogContentId) {

		if(!$blogContentId) {
			$this->Session->setFlash('無効なIDです。');
			$this->redirect(array('controller' => 'blog_contents', 'action' => 'index'));
		}

		if(empty($this->data)) {
			$this->data = $this->BlogCategory->getDefaultValue();
		}else {

			/* 登録処理 */
			$this->data['BlogCategory']['blog_content_id'] = $blogContentId;
			$this->data['BlogCategory']['no'] = $this->BlogCategory->getMax('no',array('BlogCategory.blog_content_id'=>$blogContentId))+1;
			$this->BlogCategory->create($this->data);

			// データを保存
			if($this->BlogCategory->save()) {
				$this->Session->setFlash('カテゴリ「'.$this->data['BlogCategory']['name'].'」を追加しました。');
				$this->BlogCategory->saveDbLog('カテゴリ「'.$this->data['BlogCategory']['name'].'」を追加しました。');
				$this->redirect(array('action' => 'index', $blogContentId));
			}else {
				$this->Session->setFlash('入力エラーです。内容を修正してください。');
			}

		}

		/* 表示設定 */
		$user = $this->AuthEx->user();
		$userModel = $this->getUserModel();
		$catOptions = array('blogContentId' => $this->blogContent['BlogContent']['id']);
		if($user[$userModel]['user_group_id'] != 1) {
			$catOptions['ownerId'] = $user[$userModel]['user_group_id'];
		}
		$parents = $this->BlogCategory->getControlSource('parent_id', $catOptions);
		if($this->checkRootEditable()) {
			if($parents) {
				$parents = array('' => '指定しない') + $parents;
			} else {
				$parents = array('' => '指定しない');
			}
		}
		$this->set('parents', $parents);
		$this->pageTitle = '['.$this->blogContent['BlogContent']['title'].'] 新規ブログカテゴリ登録';
		$this->help = 'blog_categories_form';
		$this->render('form');

	}
/**
 * [ADMIN] 編集処理
 *
 * @param int $blogContentId
 * @param int $id
 * @return void
 * @access public
 */
	function admin_edit($blogContentId,$id) {

		/* 除外処理 */
		if(!$id && empty($this->data)) {
			$this->Session->setFlash('無効なIDです。');
			$this->redirect(array('action' => 'index'));
		}

		if(empty($this->data)) {
			$this->data = $this->BlogCategory->read(null, $id);
		}else {

			/* 更新処理 */
			if($this->BlogCategory->save($this->data)) {
				$this->Session->setFlash('カテゴリ「'.$this->data['BlogCategory']['name'].'」を更新しました。');
				$this->BlogCategory->saveDbLog('カテゴリ「'.$this->data['BlogCategory']['name'].'」を更新しました。');
				$this->redirect(array('action' => 'index', $blogContentId));
			}else {
				$this->Session->setFlash('入力エラーです。内容を修正してください。');
			}

		}

		/* 表示設定 */
		$user = $this->AuthEx->user();
		$userModel = $this->getUserModel();
		$catOptions = array(
			'blogContentId' => $this->blogContent['BlogContent']['id'],
			'excludeParentId' => $this->data['BlogCategory']['id']
		);
		if($user[$userModel]['user_group_id'] != 1) {
			$catOptions['ownerId'] = $user[$userModel]['user_group_id'];
		}
		$parents = $this->BlogCategory->getControlSource('parent_id', $catOptions);
		if($this->checkRootEditable()) {
			if($parents) {
				$parents = array('' => '指定しない') + $parents;
			} else {
				$parents = array('' => '指定しない');
			}
		}
		$this->set('parents', $parents);
		$this->pageTitle = '['.$this->blogContent['BlogContent']['title'].'] ブログカテゴリ編集';
		$this->help = 'blog_categories_form';
		$this->render('form');

	}
/**
 * [ADMIN] 一括削除
 *
 * @param int $blogContentId
 * @param int $id
 * @return	void
 * @access public
 */
	function _batch_del($ids) {
		
		if($ids) {
			foreach($ids as $id) {
				$this->_del($id);
			}
		}
		return true;
	}
/**
 * [ADMIN] 削除処理　(ajax)
 *
 * @param int $blogContentId
 * @param int $id
 * @return	void
 * @access public
 */
	function admin_ajax_delete($blogContentId, $id = null) {

		/* 除外処理 */
		if(!$id) {
			exit();
		}
		
		if($this->_del($id)){
			exit(true);
		}else{
			exit();
		}
		
	}
/**
 * 削除処理
 *
 * @param int $blogContentId
 * @param int $id
 * @return	void
 * @access public
 */
	function _del($id = null) {
	
		// メッセージ用にデータを取得
		$data = $this->BlogCategory->read(null, $id);
		/* 削除処理 */
		if($this->BlogCategory->del($id)) {
	
			$this->BlogCategory->saveDbLog('カテゴリ「'.$data['BlogCategory']['name'].'」を削除しました。');
			return true;
		}else {
			return false;
		}
		
	}
	
/**
 * [ADMIN] 削除処理
 *
 * @param int $blogContentId
 * @param int $id
 * @return	void
 * @access public
 */
	function admin_delete($blogContentId, $id = null) {

		/* 除外処理 */
		if(!$id) {
			$this->Session->setFlash('無効なIDです。');
			$this->redirect(array('action' => 'index'));
		}

		// メッセージ用にデータを取得
		$post = $this->BlogCategory->read(null, $id);

		/* 削除処理 */
		if($this->BlogCategory->del($id)) {
			$this->Session->setFlash($post['BlogCategory']['name'].' を削除しました。');
			$this->BlogCategory->saveDbLog('カテゴリ「'.$post['BlogCategory']['name'].'」を削除しました。');
		}else {
			$this->Session->setFlash('データベース処理中にエラーが発生しました。');
		}

		$this->redirect(array('action'=>'index',$blogContentId));

	}
	
}
?>