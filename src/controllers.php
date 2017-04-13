<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// general home page
$app->get('/', function () use ($app) {
    // This was just testing to make sure the database works
    // $sql = "select * from account";
    // $rows = $app['db']->fetchAll($sql);
    // $app['monolog']->info(sprintf("found %d accounts.", count($rows)));

    $app['monolog']->info('[/] pre-token check');
    $token = $app['security.token_storage']->getToken();
    if ($token !== null) {
        $app['monolog']->info('[/] found a login');
        if ($app['security.authorization_checker']->isGranted('ROLE_ADMIN')) {
            $app['monolog']->info('[/] redirecting to admin');
            $app->redirect(url('admin'));
        }
    }

    return $app['twig']->render('index.html.twig');
})->bind('homepage');

// the admin home page
$app->get('/admin', function () use ($app) {
    $pending = $app['db']->fetchAll("select * from account where account.status = 1");
    $avg_gender = $app['db']->fetchAll("select gender,avg(value) as avg,stddev(value) as stddev from account,makes,transaction where account.type = 0 and account.id = makes.toacc and makes.tid = transaction.id and transaction.memo = 'Salary Payment' group by gender order by gender asc");
    $liked = $app['db']->fetchAll("select * from (select likes.smid, fromacc, toacc, text, count(distinct who) as count from likes,social_media_post,makes where likes.smid = social_media_post.id and likes.smid = makes.smid group by likes.smid, fromacc, toacc, text order by count(distinct who) desc) where ROWNUM <= 10");
    $salary_by_city = $app['db']->fetchAll("select address_city, address_state, avg(value) as avg, stddev(value) as stddev from account,makes,transaction where account.type = 0 and account.id = makes.toacc and makes.tid = transaction.id and transaction.memo = 'Salary Payment' group by account.address_city, account.address_state order by avg(value) desc");
    $friends = $app['db']->fetchAll("select * from (select friend1, count(distinct friend2) as count from is_friends_with group by friend1 order by count(distinct friend2) desc) where ROWNUM <= 10");
    $money = $app['db']->fetchAll("select * from (select fromacc, pos-neg as total from (select fromacc, sum(value) as neg from makes,transaction where makes.tid = transaction.id group by fromacc), (select toacc, sum(value) as pos from makes,transaction where makes.tid = transaction.id group by toacc) where fromacc = toacc and fromacc != 1337 order by pos-neg desc) where rownum <= 10");
    $year = $app['db']->fetchAll("select * from (select extract(year from dob) as year, avg(value) as avg from account,makes,transaction where account.id = makes.toacc and makes.tid = transaction.id and makes.toacc != 1337 and transaction.memo = 'Salary Payment' group by extract(year from dob) order by avg(value) desc) where rownum <= 10");
    $count = $app['db']->fetchAssoc("select c0+c1+c2+c3+c4+c5+c6+c7 as tot from (select count(*) as c0 from account),(select count(*) as c1 from is_friends_with),(select count(*) as c2 from reply),(select count(*) as c3 from social_media_post),(select count(*) as c4 from likes),(select count(*) as c5 from transaction),(select count(*) as c6 from posts),(select count(*) as c7 from makes)");
    return $app['twig']->render('admin_home.html.twig', array(
        $avg_gender[0]["GENDER"].'Count' => $avg_gender[0]["AVG"],
        $avg_gender[1]["GENDER"].'Count' => $avg_gender[1]["AVG"],
        'salarybycity' => $salary_by_city,
        'friends' => $friends,
        'likes' => $liked,
        'pending' => $pending,
        'money' => $money,
        'years' => $year,
        'count' => $count['TOT'],
    ));
})->bind('admin_home');

// admin search page
$app->get('/admin/search', function (Request $req) use ($app) {
    $qq = $req->query->get('q');
    if ($qq) {
        $results = $app['db']->fetchAll("select fromacc as id, pos-neg as money, name, email_address, dob, address_city, address_state from account,(select fromacc, sum(value) as neg from makes,transaction where makes.tid = transaction.id group by fromacc), (select toacc, sum(value) as pos from makes,transaction where makes.tid = transaction.id group by toacc) where fromacc = toacc and fromacc = account.id and fromacc != 1337 and (name like '%$qq%' or email_address like '%$qq%' or id like '%$qq%' or address_city like '%$qq%' or address_state like '%$qq%') order by id asc");
        return $app['twig']->render('admin_search.html.twig', array(
            'count' => count($results),
            'results' => $results,
        ));
    } else {
        return $app['twig']->render('admin_search.html.twig', array(
            'count' => 0,
            'results' => null,
        ));
    }
})->bind('admin_search');

// user home page
$app->get('/user', function () use ($app) {
$token = $app['security.token_storage']->getToken();
if (null !== $token) {
    $user = $token->getUser();
}
    $topTen= $app['db']->fetchAll("select * from (select timestamp, type, value, ID from Transaction, Makes where (fromacc = 1337 or toacc= 1337) and transaction.id=makes.tid order by timestamp desc) where rownum <=10");
    $userID= $app['db'] -> fetchAssoc("select name from account where email_address = '$user'");
	   
return $app['twig']->render('user.html.twig', array(
	
	"topTen" => $topTen,
	"userID" => $userID
	));
})->bind('user_home');

// user timeline
$app->get('/user/timeline', function () use ($app) {
    $token = $app['security.token_storage']->getToken();
    if (null !== $token) {
        $user = $token->getUser();
        $name = $app['db']->fetchAssoc("select name, id from account where email_address = '$user'");
        $posts = $app['db']->fetchAll("select makes.tid as tid,a1.name as fromname,a2.name as toname,transaction.value as value, transaction.timestamp as tstamp, social_media_post.text as text from social_media_post,makes,transaction,account a1,account a2 where a1.id = makes.fromacc and a2.id = makes.toacc and social_media_post.id = makes.smid and makes.tid = transaction.id and (makes.fromacc in (select friend2 from is_friends_with where friend1 = ?) or makes.toacc in (select friend2 from is_friends_with where friend1 = ?)) order by transaction.timestamp desc", array($name['ID'],$name['ID']));
        return $app['twig']->render('user_timeline.html.twig', array(
            'name' => $name['NAME'],
            'posts' => $posts,
        ));
    } else {
        $app->abort(403);
    }
})->bind('user_timeline');

$app->get('/user/timeline/{id}', function ($id) use ($app) {
    $token = $app['security.token_storage']->getToken();
    if (null !== $token) {
        $user = $token->getUser();
        $name = $app['db']->fetchAssoc("select name, id from account where email_address = '$user'");
        $tid = $app['db']->fetchAssoc("select transaction.id as tid,sm.id as smid,sm.text as text,transaction.value as value,transaction.timestamp as tstamp,transaction.memo as memo,a1.name as fromacc,a2.name as toacc,a1.id as fromaccid,a2.id as toaccid from social_media_post sm,transaction,makes,account a1,account a2 where sm.id = makes.smid and a1.id = makes.fromacc and a2.id = makes.toacc and transaction.id = $id and transaction.id = makes.tid and (makes.fromacc in (select friend2 from is_friends_with where friend1 = ?) or makes.toacc in (select friend2 from is_friends_with where friend1 = ?))", array($name['ID'], $name['ID']));
        $likes = $app['db']->fetchAssoc("select count(*) as likes from likes where likes.smid = ?", array($tid['SMID']));
        $comments = $app['db']->fetchAll("select * from reply,posts,account where posts.who = account.id and posts.sm_post = ? and reply.id = posts.message and (posts.who = ? or posts.who in (select friend2 from is_friends_with where friend1 = ?))", array($tid['SMID'], $name['ID'], $name['ID']));
        if (count($tid) <= 0)
            $app->abort(403);
        else
            return $app['twig']->render('user_timeline_id.html.twig', array(
                'tid' => $tid,
                'comments' => $comments,
                'likes' => $likes['LIKES'],
            ));
    } else {
        $app->abort(403);
    }
})->bind('user_timeline_id');

$app->post('/user/timeline/{id}', function ($id) use ($app) {
    $token = $app['security.token_storage']->getToken();
    if (null !== $token) {
        $user = $token->getUser();
        $name = $app['db']->fetchAssoc("select name, id from account where email_address = '$user'");
        $smid = $app['db']->fetchAssoc("select smid from makes where tid = $id");
        try {
            $liked = $app['db']->fetchAssoc("insert into likes values(?, ?)", array($name['ID'], $smid['SMID']));
        } catch (Exception $e) {}
        return $app->redirect('/user/timeline/'.$id);
    } else {
        $app->abort(403);
    }
});

$app->post('/user/comment/{smid}/{tid}/{id}', function ($smid, $tid, $id, Request $req) use ($app) {
    $token = $app['security.token_storage']->getToken();
    if (null !== $token) {
        $user = $token->getUser();
        $name = $app['db']->fetchAssoc("select name, id from account where email_address = '$user'");
        $info = $req->request->get('comment_text');
        $reply = $app['db']->fetchAssoc("insert into reply values(seq_reply.nextval, CURRENT_TIMESTAMP, ?)", array($info));
        $posts = $app['db']->fetchAssoc("insert into posts values(?, seq_reply.currval, ?)", array($name['ID'], $smid));
        return $app->redirect('/user/timeline/'.$tid);
    } else {
        $app->abort(403);
    }
})->bind('user_comment');

// user profile page
$app->get('/user/{id}', function ($id) use ($app) {
    $token = $app['security.token_storage']->getToken();
    if ($token !== null) {
        $user = $token->getUser();
        $name = $app['db']->fetchAssoc("select name, id from account where email_address = '$user'");
        $friends = $app['db']->fetchAssoc("select account.name from is_friends_with, account where friend1 = account.id and friend1 = ? and friend2 = ?", array($id, $name['ID']));
        if (!$friends && $name['ID'] != 1337)
            return $app->redirect('/user');

        $posts = $app['db']->fetchAll("select * from (select social_media_post.id, social_media_post.timestamp, text from social_media_post, account, makes where social_media_post.id = makes.smid and (makes.fromacc = account.id or makes.toacc = account.id) and account.id = ? order by timestamp desc) where rownum <= 10", array($id));
        if ($friends)
            $username = $friends['NAME'];
        else {
            $fname = $app['db']->fetchAssoc("select name from account where id = $id");
            $username = $fname['NAME'];
        }
        return $app['twig']->render('user_profile.html.twig', array(
            "username" => $username,
            "posts" => $posts
        ));
    } else {
        $app->abort(403);
    }
})->bind('user_profile');

// user edit page (can only view your own, or admin can view all)
$app->get('/user/edit/{id}', function ($id) use ($app) {
    return $app['twig']->render('index.html.twig');
})->bind('user_edit');

// login page
$app->get('/login', function (Request $request) use ($app) {
    return $app['twig']->render('login.html.twig', array(
        'error' => $app['security.last_error']($request),
        'last_username' => $app['session']->get('_security.last_username'),
    ));
})->bind('login');

// admin login page
$app->get('/secret/login', function (Request $request) use ($app) {
    return $app['twig']->render('secret_login.html.twig', array(
        'error' => $app['security.last_error']($request),
        'last_username' => $app['session']->get('_security.last_username'),
    ));
})->bind('secret_login');

// error handler
$app->error(function (\Exception $e, Request $request, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    // 404.html, or 40x.html, or 4xx.html, or error.html
    $templates = array(
        'errors/'.$code.'.html.twig',
        'errors/'.substr($code, 0, 2).'x.html.twig',
        'errors/'.substr($code, 0, 1).'xx.html.twig',
        'errors/default.html.twig',
    );

    return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});
