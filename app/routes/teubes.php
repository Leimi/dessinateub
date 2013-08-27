<?php
use RedBean_Facade as R;

/**
 * LISTE
 *
 */
function listTeubes() {
	$app = \Slim\Slim::getInstance();

	$page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT);
	$sort = filter_input(INPUT_GET, 'ordre', FILTER_SANITIZE_STRING);

	if (empty($page) || !is_numeric($page) || $page <= 0)
		$page = 1;

	$itemsNb = 15;
	$limit = ($page - 1)*$itemsNb;

	$teu = getTeubes($sort, $limit, $itemsNb);

	$app->render('list.php', array(
		'page' => 'list',
		'teubes' => $teu["teubes"],
		'title' => $teu["title"],
		'pageNb' => $page,
		'fullList' => count($teu['teubes']) == $itemsNb
	));
}

function getTeubes($sort, $limit, $itemsNb) {
	switch ($sort) {
		case 'anciennes':
			$field = "created";
			$order = "ASC";
			$title = "Les plus anciennes teubs";
			break;
		case 'belles':
			$field = "w_rating";
			$order = "DESC";
			$title = "Les teubs préférées de la communauté";
			break;
		case 'moches':
			$field = "w_rating";
			$order = "ASC";
			$title = "Les plus moches";
			break;
		case 'kamoulox':
			$field = "comments_count";
			$order = "DESC";
			$title = "Les teubs dont on débat le plus";
			break;
		case 'nouvelles':
		default:
			$field = "created";
			$order = "DESC";
			$title = "Les dernières créations";
			break;
	}

	$teubes = R::findAll('teube', '
		ORDER BY
			CASE WHEN '.$field.' IS NULL THEN 1 ELSE 0 END,
			'.$field.' '.$order.'
		LIMIT '.$limit.','.$itemsNb
	);

	return array('title' => $title, 'teubes' => $teubes, 'sort' => $sort);
}
$app->get('/mater', 'listTeubes')->name('teubes');


/**
 * CREATION
 *
 */
$app->get('/etjelemontre', function() use ($app) {
	$app->render('draw.php', array('page' => 'draw'));
})->name('draw');


/**
 * EDITION
 *
 */
$app->get('/etjelemontre/:slug', function($slug) use ($app) {
	$teube = null;
	$slug = filter_var($slug, FILTER_SANITIZE_NUMBER_INT);
	if (is_numeric($slug) && !empty($_SESSION['ids']) && in_array($slug, $_SESSION['ids']))
		$teube = R::load('teube', $slug);
	else {
		$app->flash('info', "Impossible de modifier cette teub");
		$app->redirect($app->request()->getReferrer());
	}
	$app->render('draw.php', array('page' => 'draw', 'teube' => $teube));
})->name('draw-edit');


/**
 * CREATION (POST)
 *
 */
$app->post('/etjelemontre', function() use ($app) {
	$teube = R::dispense('teube');
	$teube->name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
	$teube->artist = filter_input(INPUT_POST, 'artist', FILTER_SANITIZE_STRING);
	$teube->image = filter_input(INPUT_POST, 'image', FILTER_SANITIZE_URL);
	$teube->votes = 0;
	$teube->active = 0;
	if (!empty($teube->name) && !empty($teube->artist) && !empty($teube->image)) {
		$teubeId = R::store($teube);
		if (isset($_SESSION['ids']))
			$_SESSION['ids'][]= $teubeId;
		else
			$_SESSION['ids'] = array($teubeId);
		$app->flash('success', "Teub ajoutée ! Tu peux la modifier ou la supprimer pendant encore quelques minutes.");
		$app->redirect($app->urlFor('regarder', array('slug' => $teube->id)));
	}
	else {
		$app->flash('error', "Erreur : t'es sûr d'avoir bien dessiné, nommé et signé ta teub ?");
		$app->redirect($app->request()->getReferrer());
	}
})->name('draw-post');


/**
 * EDITION (PUT)
 */
$app->put('/etjelemontre/:slug', function($slug) use($app) {
	$slug = filter_var($slug, FILTER_SANITIZE_NUMBER_INT);
	$teube = R::load('teube', $slug);
	if (!empty($teube->id) && !empty($_SESSION['ids']) && in_array($teube->id, $_SESSION['ids'])) {
		$teube->name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
		$teube->artist = filter_input(INPUT_POST, 'artist', FILTER_SANITIZE_STRING);
		$teube->image = filter_input(INPUT_POST, 'image', FILTER_SANITIZE_URL);
		R::store($teube);
		$app->flash('success', "Teub modifiée !");
	} else {
		$app->flash('info', "Impossible de modifier cette teub");
	}
	$app->redirect($app->urlFor('regarder', array('slug' => $teube->id)));
})->name('draw-put');


/**
 * SUPPRESSION
 */
$app->delete('/etjelemontre/:slug', function($slug) use($app) {
	$slug = filter_var($slug, FILTER_SANITIZE_NUMBER_INT);
	$teube = R::load('teube', $slug);
	if (!empty($teube->id) && !empty($_SESSION['ids']) && in_array($teube->id, $_SESSION['ids'])) {
		R::trash($teube);
		$app->flash('success', "Teub supprimée !");
	} else {
		$app->flash('info', "Impossible de supprimer cette teub");
	}
	$app->redirect('home');
})->name('draw-delete');


/**
 * VUE
 */
$app->get('/regarder/:slug', function($slug) use($app) {
	$slug = filter_var($slug, FILTER_SANITIZE_NUMBER_INT);
	$teube = R::load('teube', $slug);
	$isEditable = (!empty($_SESSION['ids']) && in_array($teube->id, $_SESSION['ids']));
	$userVote = $teube->getUserVote();
	$app->render('view.php', array('page' => 'view', 'teube' => $teube, 'userVote' => $userVote, 'isEditable' => $isEditable));
})->name('regarder');


/**
 * SIGNALER UN ABUS
 */
$app->post('/NANMAISCAVAPAS/:slug', function($slug) use($app) {
	$slug = filter_var($slug, FILTER_SANITIZE_NUMBER_INT);
	$teube = R::load('teube', $slug);
	if ($teube->id)
		$teube->report();
	$app->flash('success', "Merci, on va voir ce qu'on fait d'elle.");
	$app->redirect($app->request()->getReferrer());
})->name('abus');

/**
 * RANDOM
 */
$app->get('/balancebalancebalancebalancetoi', function() use($app) {
	$max = R::getCell('SELECT id FROM teube ORDER BY id DESC limit 1');
	$app->redirect($app->urlFor('regarder', array('slug' => mt_rand(1, $max))));
})->name('random');