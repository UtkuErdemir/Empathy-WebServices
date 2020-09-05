<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require 'vendor/autoload.php';
$c['notFoundHandler'] = function ($c) {
    return function ($request, $response) use ($c) {
        return $c['response']
            ->withStatus(404)
            ->withHeader('Content-Type', 'text/html')
            ->write('Üzgünüm yanlış sayfalarda gezinmektesiniz :)');
    };
};

$app = new \Slim\App($c);
$app->get('/user/e_mail={e_mail}/pwd={pass}/key={key}', function (Request $request, Response $response, array $args) use ($mysqli) {
    if(validateSecretKey($args['key'])){
    require_once ('dbconnect.php');
    $e_mail= $args['e_mail'];
    $pass= md5($args['pass']);
    $query = "Select u_id,name,surname,rating,sub_rating,reg_date,time,question_count From users u Where u.e_mail='{$e_mail}' AND u.password='{$pass}' AND u.active=1";
    $result = $mysqli->query($query);
    while($row= $result->fetch_assoc())
    {
        $data['error'] = false;
        $data['user'] = $row;
    }
    if(!isset($data)){
        $data['error'] = true;
        $data['message'] = "Geçersiz kullanıcı numarası / şifre girdiniz.";
    }
    }
    else{
        $data['error'] = true;
        $data['message'] = "Geçersiz anahtar girdiniz.";
    }
    echo json_encode($data);
    return $response;
});
$app->get('/pop_quiz/rating={rating}/key={key}', function (Request $request, Response $response, array $args) use ($mysqli) {
    if(validateSecretKey($args['key'])) {
        require_once('dbconnect.php');
        $rating = $args['rating'];
        $query = "Select * From questions q Where q.rating<" . $rating;
        $result = $mysqli->query($query);
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
            $array['error'] = false;
            $array['questions'] = array_values($data);
        }
        if (!isset($array)) {
            $array['error'] = true;
            $array['message'] = "Geçersiz bir seviye girdiniz.";
        }
        echo json_encode($array);
    }
    else{
        $array['error'] = true;
        $array['message'] = "Geçersiz anahtar girdiniz.";
    }
    echo json_encode($array);
    return $response;
});
$app->get('/quiz/rating={rating}/sub_rating={sub_rating}/key={key}', function (Request $request, Response $response, array $args) use ($mysqli) {
    if(validateSecretKey($args['key'])) {
        require_once('dbconnect.php');
        $rating = $args['rating'];
        $sub_rating = $args['sub_rating'];
        $query = "Select * From questions q Where q.rating ={$rating} AND q.sub_rating={$sub_rating}";
        $result = $mysqli->query($query);
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
            $array['error'] = false;
            $array['questions'] = array_values($data);
        }
        if (!isset($array)) {
            $array['error'] = true;
            $array['message'] = "Geçersiz bir seviye girdiniz.";
        }
    }
    else{
        $array['error'] = true;
        $array['message'] = "Geçersiz anahtar girdiniz.";
    }
    echo json_encode($array);
    return $response;
});
$app->get('/contents/rating={rating}/sub_rating={sub_rating}/key={key}', function (Request $request, Response $response, array $args) use ($mysqli) {
    if(validateSecretKey($args['key'])) {
        require_once('dbconnect.php');
        $rating = $args['rating'];
        $sub_rating = $args['sub_rating'];
        $query = "Select * From contents c Where c.rating=" . $rating . " AND c.sub_rating =" . $sub_rating;
        $result = $mysqli->query($query);
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
            $array['error'] = false;
            $array['contents'] = array_values($data);
        }
        if (!isset($array)) {
            $array['error'] = true;
            $array['message'] = "Geçersiz bir konu seviyesi girdiniz.";
        }
    }
    else{
        $array['error'] = true;
        $array['message'] = "Geçersiz anahtar girdiniz.";
    }
    echo json_encode(utf8ize($array));
    return $response;
});
function utf8ize($d) {
    if (is_array($d)) {
        foreach ($d as $k => $v) {
            $d[$k] = utf8ize($v);
        }
    } else if (is_string ($d)) {
        return utf8_encode($d);
    }
    return $d;
}
$app->get('/topics/key={key}', function (Request $request, Response $response, array $args) use ($mysqli) {
    if(validateSecretKey($args['key'])) {
        require_once('dbconnect.php');
        $query = "Select * From topics ORDER BY rating";
        $result = $mysqli->query($query);
        $array['error'] = false;
        while ($row = $result->fetch_assoc()) {
            $data['topic'] = $row;
            $data['topic']['sub_topics'] = getSubTopics($row['rating'], $mysqli);
            $array['topics'][] = $data;
        }
        echo json_encode($array);
    }
    else{
        $data['error'] = true;
        $data['message'] = "Geçersiz anahtar girdiniz.";
        echo json_encode($data);
    }
    return $response;
});
function getSubTopics($value, $mysqli){
    $query="Select rating,sub_rating,st_name From sub_topics st Where st.rating={$value} ORDER BY sub_rating";
    $result=$mysqli->query($query);
    while($row= $result->fetch_assoc())
    {
        $data[] = $row;
    }
    return $data;
}
$app->post('/register/key={key}', function (Request $request, Response $response, array $args) use ($mysqli) {
    if(validateSecretKey($args['key'])) {
        require_once('dbconnect.php');
        $e_mail = $request->getParsedBody()['e_mail'];
        $password = $request->getParsedBody()['password'];
        if (validatePassword($password)) {
            if(validateEmail($e_mail,$mysqli)){
            $password = md5($request->getParsedBody()['password']);
            $name = $request->getParsedBody()['name'];
            $surname = $request->getParsedBody()['surname'];
            date_default_timezone_set('Turkey');
            $date = date('Y/m/d H:i:s', time());
            $rand_num=rand(1, 1000000);
            $token= md5($name.$rand_num);
            $query = "INSERT INTO `users` (`e_mail`, `password`, `name`, `surname`, `rating`, `sub_rating`, `reg_date`, `time`, `question_count`, `active`,`token`) VALUES ('{$e_mail}','{$password}','{$name}','{$surname}', 1, 1, '{$date}' , 0  , 0, 0,'{$token}');";
            $result = $mysqli->prepare($query);
            $result->execute();
            if(mysqli_affected_rows($mysqli) >0){
                $data['error'] = false;
                $data['message'] = "Kullanıcı başarıyla kaydedildi. E-Posta adresinize gönderdiğimiz linke tıklayarak üyeliğinizi aktif edebilirsiniz.";
                //sendEmail($e_mail,$token);
            }
            else{
                $data['error'] = true;
                $data['message'] = "Bir problem oluştu.";
            }
            }
            else{
                $data['error'] = true;
                $data['message'] = "Geçersiz veya kayıtlı bir e-posta adresi girdiniz.";
            }
        }
        else{
            $data['error'] = true;
            $data['message'] = "Geçersiz biçimde şifre girdiniz.";
        }
    }
    else{
        $data['error'] = true;
        $data['message'] = "Geçersiz anahtar girdiniz.";
    }
    echo json_encode($data);
    return $response;
});
$app->put('/changePassword/{id}/key={key}', function (Request $request, Response $response, array $args) use ($mysqli) {
    if(validateSecretKey($args['key'])) {
        require_once('dbconnect.php');
        $user_id = $args['id'];
        $old_password = $request->getParsedBody()['old_password'];
        $password = $request->getParsedBody()['password'];
        $crypted_old_password = md5($old_password);
        $query = "Select rating From users u Where u.u_id=" . $user_id . " AND u.password='{$crypted_old_password}'";
        $result = $mysqli->query($query);
        if ($result->num_rows > 0) {
            if (validatePassword($password)) {
                $password = md5($request->getParsedBody()['password']);
                $query = "Update users SET password='{$password}'Where u_id=" . $user_id;
                $command = $mysqli->prepare($query);
                $command->execute();
                if(mysqli_affected_rows($mysqli) >0){
                $data['error'] = false;
                $data['message'] = "Şifreniz başarıyla değiştirildi.";
                }
                else{
                    $data['error'] = true;
                    $data['message'] = "Bir problem oluştu.";
                }
            }
            else{
                $data['error'] = true;
                $data['message'] = "Şifreniz istenilen kritelere uymamaktadır.";
            }
        }
    }
    else{
        $data['error'] = true;
        $data['message'] = "Geçersiz anahtar girdiniz.";
    }
    echo json_encode($data);
    return $response;
});
$app->put('/changeEmail/{id}/key={key}', function (Request $request, Response $response, array $args) use ($mysqli) {
    if(validateSecretKey($args['key'])) {
        require_once('dbconnect.php');
        $user_id = $args['id'];
        $e_mail = $request->getParsedBody()['e_mail'];
        $password = md5($request->getParsedBody()['password']);
        if (validateEmail($e_mail,$mysqli)) {
            $query = "Update users SET e_mail='{$e_mail}'Where u_id='{$user_id}' AND password=" . $password;
            $result = $mysqli->prepare($query);
            $result->execute();
            if(mysqli_affected_rows($mysqli) >0) {
                $data['error'] = false;
                $data['message'] = "E-posta adresiniz başarıyla değiştirilmiştir.";
            }
            else{
                $data['error'] = true;
                $data['message'] = "Bir problem oluştu.";
            }
        }
        else{
            $data['error'] = true;
            $data['message'] = "Geçersiz veya başka bir hesaba kayıtlı bir e-posta adresi girdiniz.";
        }
    }
    else{
        $data['error'] = true;
        $data['message'] = "Geçersiz anahtar girdiniz.";
    }
    echo json_encode($data);
    return $response;
});
$app->put('/changeFullName/{id}/key={key}', function (Request $request, Response $response, array $args) use ($mysqli) {
    if(validateSecretKey($args['key'])) {
        require_once('dbconnect.php');
        $user_id = $args['id'];
        $name = $request->getParsedBody()['name'];
        $surname = $request->getParsedBody()['surname'];
        $query = "Update users SET name='{$name}', surname='{$surname}' Where u_id=" . $user_id;
        $result = $mysqli->prepare($query);
        $result->execute();
        if(mysqli_affected_rows($mysqli) >0) {
            $data['error'] = false;
            $data['message'] = "İsminiz ve Soyisminiz başarıyla değiştirilmiştir.";
        }
        else{
            $data['error'] = true;
            $data['message'] = "Bir problem oluştu.";
        }
    }
    else{
        $data['error'] = true;
        $data['message'] = "Geçersiz anahtar girdiniz.";
    }
    echo json_encode($data);
    return $response;
});
$app->put('/changeLevel/{id}/key={key}', function (Request $request, Response $response, array $args) use ($mysqli) {
    if(validateSecretKey($args['key'])) {
        require_once('dbconnect.php');
        $user_id = $args['id'];
        $rating = $request->getParsedBody()['rating'];
        $sub_rating = $request->getParsedBody()['sub_rating'];
        $query = "Update users SET rating='{$rating}', sub_rating='{$sub_rating}' Where u_id=" . $user_id;
        $result = $mysqli->prepare($query);
        $result->execute();
        if(mysqli_affected_rows($mysqli) >0) {
            $data['error'] = false;
            $data['message'] = "Seviye başarıyla değiştirilmiştir.";
        }
        else{
            $data['error'] = true;
            $data['message'] = "Bir problem oluştu.";
        }
    }
    else{
        $data['error'] = true;
        $data['message'] = "Geçersiz anahtar girdiniz.";
    }
    echo json_encode($data);
    return $response;
});
$app->put('/changeTime/{id}/key={key}', function (Request $request, Response $response, array $args) use ($mysqli) {
    if(validateSecretKey($args['key'])) {
        require_once('dbconnect.php');
        $user_id = $args['id'];
        $time = $request->getParsedBody()['time'];
        $query = "Update users SET time='{$time}' Where u_id=" . $user_id;
        $result = $mysqli->prepare($query);
        $result->execute();
        if(mysqli_affected_rows($mysqli) >0) {
            $data['error'] = false;
            $data['message'] = "Süre başarıyla değiştirilmiştir.";
        }
        else{
            $data['error'] = true;
            $data['message'] = "Bir problem oluştu.";
        }
    }
    else{
        $data['error'] = true;
        $data['message'] = "Geçersiz anahtar girdiniz.";
    }
    echo json_encode($data);
    return $response;
});
$app->put('/changeCount/{id}/key={key}', function (Request $request, Response $response, array $args) use ($mysqli) {
    if(validateSecretKey($args['key'])) {
        require_once('dbconnect.php');
        $user_id = $args['id'];
        $count = $request->getParsedBody()['count'];
        $query = "Update users SET question_count='{$count}' Where u_id=" . $user_id;
        $result = $mysqli->prepare($query);
        $result->execute();
        if(mysqli_affected_rows($mysqli) >0) {
            $data['error'] = false;
            $data['message'] = "Soru sayısı başarıyla değiştirilmiştir.";
        }
        else{
            $data['error'] = true;
            $data['message'] = "Bir problem oluştu.";
        }
    }
    else{
        $data['error'] = true;
        $data['message'] = "Geçersiz anahtar girdiniz.";
    }
    echo json_encode($data);
    return $response;
});
function validatePassword($password){
    if(strlen($password)>=5 && strlen($password)<20 && preg_match("#[0-9]+#",$password) && preg_match("#[A-Z]+#",$password) && preg_match("#[a-z]+#",$password)){
        return true;
    }
    else{
        return false;
    }
}
function validateSecretKey($secret_key){
    $key = "6553a4bbbde59ff3f3dd73aaa3ac8975";
    if($secret_key == $key)
    {
        return true;
    }
    else{
        return false;
    }
}
function validateEmail($e_mail, $mysqli){
    if(filter_var($e_mail, FILTER_VALIDATE_EMAIL))
    {
        $query="Select e_mail From users Where e_mail='{$e_mail}'";
        $result=$mysqli->query($query);
        echo $mysqli->error;
        while($row= $result->fetch_assoc())
        {
            $data[] = $row;
        }
        if(!isset($data)){
            return true;
        }
        else{
            return false;
        }
    }
    else{
        return false;
    }
}
function sendEmail($email,$token){
    $to      = $email;
    $subject = 'Empati Uygulaması Kayıt Onaylama';
    $message = '
Kayıt Olduğunuz İçin Teşekkür Ederiz!
Üyelik kaydınız oluşturuldu fakat siz aşağıdaki linke tıklamazsanız üyeliğiniz aktif olmayacaktır.
 

Lütfen üyeliğinizi onaylamak için aşağıdaki linke tıklayınız.:
http://www.yourwebsite.com/verify/email='.$email.'/token='.$token.'
 
';

    $headers = 'From:noreply@yourwebsite.com' . "\r\n";
    mail($to, $subject, $message, $headers);
}
$app->run();
