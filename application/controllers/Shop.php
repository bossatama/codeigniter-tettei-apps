<?php
/* 簡易ショッピングカート
 * 
 */

/**
 * @property CI_Session      $session
 * @property Shop_model      $Shop_model
 * @property Inventory_model $Inventory_model
 * @property Cart_model      $Cart_model
 * @property Customer_model  $Customer_model
 */
class Shop extends CI_Controller {

	public $limit;	// 1ページに表示する商品の数
	public $admin;	// 管理者のメールアドレス

	public function __construct()
	{
		parent::__construct();
		$this->load->library('session');
		$this->load->helper(['form', 'url']);

# モデルをロードします。ロード後のモデルオブジェクトは、$this->Shop_model
# として利用できます。
		$this->load->model('shop/Shop_model');
		$this->load->model('shop/Inventory_model');
		$this->load->model('shop/Cart_model');
		$this->load->model('shop/Customer_model');

# このアプリケーション専用の設定ファイルconfig_shop.phpを読み込みます。
# load()メソッドの第2引数にTRUEを指定すると、他の設定ファイルで使われている
# 設定項目名との衝突を気にしなくても済みます。
		$this->config->load('config_shop', TRUE);
# 上記のように読み込んだ場合、設定値は、以下のようにitem()メソッドに引数で
# 「設定項目名」と「設定ファイル名」を渡すことで取得できます。
		$this->limit = $this->config->item('per_page', 'config_shop');
		$this->admin = $this->config->item('admin_email', 'config_shop');

		$this->output->set_header('Content-Type: text/html; charset=UTF-8');

		//$this->output->enable_profiler(TRUE);
	}

	// トップページ = カテゴリ別商品一覧
	public function index()
	{
# モデルからカテゴリの一覧を取得し、shop_menuビューに渡します。このとき、
# view()メソッドの第2引数にTRUEを指定することで、処理されたページデータを
# ブラウザに送信させずに、文字列として取得し、変数に代入します。
		$data['list'] = $this->Inventory_model->get_category_list();
		$data['menu'] = $this->load->view('shop_menu', $data, TRUE);

# 3番目のURIセグメントより、カテゴリIDを取得します。セグメントデータがない
# 場合は、1を設定します。
		$cat_id = (int) $this->uri->segment(3, 1);
# 4番目のURIセグメントより、offset値を取得します。セグメントデータがない場合
# は、0を設定します。
		$offset = (int) $this->uri->segment(4, 0);

# カテゴリIDとoffset値と、1ページに表示する商品の数を渡し、モデルより
# 商品一覧を取得します。
		$data['list'] = $this->Inventory_model->get_product_list($cat_id, $this->limit, $offset);
# カテゴリIDより、カテゴリ名を取得します。
		$data['category'] = $this->Inventory_model->get_category_name($cat_id);

# モデルよりそのカテゴリの商品数を取得し、ページネーションを生成します。
		$this->load->library('Generate_pagination');
		$path  = '/shop/index/' . $cat_id;
		$total = $this->Inventory_model->get_product_count($cat_id);
		$data['pagination'] = $this->generate_pagination->get_links($path, $total, 4);

		$data['total'] = $total;

		$data['main']   = $this->load->view('shop_list', $data, TRUE);

# モデルよりカートの中の商品アイテム数を取得します。
		$data['item_count'] = $this->Cart_model->count();
# ショップヘッダのページデータを文字列として取得し、変数に代入します。
		$data['header'] = $this->load->view('shop_header', $data, TRUE);
# ビューを表示します。
		$this->load->view('shop_tmpl_shop', $data);
	}

	// 商品詳細ページ
	public function product()
	{
		$data['list'] = $this->Inventory_model->get_category_list();
		$data['menu'] = $this->load->view('shop_menu', $data, TRUE);

# 3番目のURIセグメントより、商品IDを取得します。セグメントデータがない
# 場合は、1を設定します。
		$prod_id = (int) $this->uri->segment(3, 1);
# モデルより商品データを取得します。
		$data['item'] = $this->Inventory_model->get_product_item($prod_id);
		$data['main']   = $this->load->view('shop_product', $data, TRUE);

		$data['item_count'] = $this->Cart_model->count();
		$data['header'] = $this->load->view('shop_header', $data, TRUE);
		$this->load->view('shop_tmpl_shop', $data);
	}

	// カゴに入れる
	public function add()
	{
# 3番目のURIセグメントより、商品IDを取得します。セグメントデータがない
# 場合は、0を設定します。
		$prod_id = (int) $this->uri->segment(3, 0);
# POSTされたqtyフィールドより、数量を取得します。
		$qty     = (int) $this->input->post('qty');
		$this->Cart_model->add($prod_id, $qty);

# コントローラのcart()メソッドを呼び出し、カートを表示します。
		$this->cart();
	}

	// 買い物カゴページ
	function cart()
	{
		$data['list'] = $this->Inventory_model->get_category_list();
		$data['menu'] = $this->load->view('shop_menu', $data, TRUE);

# モデルより、カートの情報を取得します。
		$cart = $this->Cart_model->get_all();
		$data['total']      = $cart['total'];
		$data['cart']       = $cart['items'];
		$data['item_count'] = $cart['line'];

		$data['main']   = $this->load->view('shop_cart', $data, TRUE);
		$data['header'] = $this->load->view('shop_header', $data, TRUE);
		$this->load->view('shop_tmpl_shop', $data);
	}

	// 検索ページ
	public function search()
	{
		$q = '';	// 検索キーワード

		$data['list'] = $this->Inventory_model->get_category_list();
		$data['menu'] = $this->load->view('shop_menu', $data, TRUE);

# 検索キーワードをクエリ文字列から取得します。
		$q = (string) $this->input->get('q');
# 全角スペースを半角スペースに変換します。
		$q = trim(mb_convert_kana($q, 's'));

# offset値を、3番目のURIセグメントより取得します。
		$offset = (int) $this->uri->segment(3, 0);

# モデルから、キーワードで検索した商品データと総件数を取得します。
		$data['list'] = $this->Inventory_model->get_product_by_search($q, $this->limit, $offset);
		$total = $this->Inventory_model->get_count_by_search($q);

# ページネーションを生成します。検索キーワードには日本語が含まれます
# ので、URLエンコードします。
		$this->load->library('Generate_pagination');
		$path  = '/shop/search';
		$data['pagination'] = $this->generate_pagination->get_links($path, $total, 3);

		$data['q'] = $q;
		$data['total'] = $total;

		$data['main']   = $this->load->view('shop_search', $data, TRUE);
		$data['item_count'] = $this->Cart_model->count();
		$data['header'] = $this->load->view('shop_header', $data, TRUE);
		$this->load->view('shop_tmpl_shop', $data);
	}

	// お客様情報入力ページ
	public function customer_info()
	{
# 検証ルールを設定します。
		$this->load->library('Shop_validation');
		$this->shop_validation->set();
		$this->form_validation->run();

		$data['action'] = 'お客様情報の入力';
		$data['main']  = $this->load->view('shop_customer_info', '', TRUE);
		$this->load->view('shop_tmpl_checkout', $data);
	}

	// 注文内容確認
	public function confirm()
	{
		$this->load->library('Shop_validation');
		$this->shop_validation->set();

		if ($this->form_validation->run() == TRUE)
		{
# 検証をパスした入力データは、モデルを使って保存します。
			$data['name']  = $this->input->post('name');
			$data['zip']   = $this->input->post('zip');
			$data['addr']  = $this->input->post('addr');
			$data['tel']   = $this->input->post('tel');
			$data['email'] = $this->input->post('email');
			$this->Customer_model->set($data);

			$cart = $this->Cart_model->get_all();
			$data['total'] = $cart['total'];
			$data['cart']  = $cart['items'];

			$data['action'] = '注文内容の確認';
			$data['main']  = $this->load->view('shop_confirm', $data, TRUE);
		}
		else
		{
			$data['action'] = 'お客様情報の入力';
			$data['main']  = $this->load->view('shop_customer_info', '', TRUE);
		}

		$this->load->view('shop_tmpl_checkout', $data);
	}

	// 注文処理
	public function order()
	{
		if ($this->Cart_model->count() == 0)
		{
			echo '買い物カゴには何も入っていません。';
		}
# モデルのorder()メソッドを呼び出し、注文データの処理を依頼します。
		else if ($this->Shop_model->order())
		{
			$data['action'] = '注文の完了';
			$data['main']   = $this->load->view('shop_thankyou', '', TRUE);
			$this->load->view('shop_tmpl_checkout', $data);
# 注文が完了したので、セッションを破棄します。
			$this->session->sess_destroy();
		}
		else
		{
			echo 'システムエラー';
		}
	}
}
