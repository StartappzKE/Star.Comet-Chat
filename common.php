<?php
/**
 * Плагин чата для сайта знакомств
 * 
 * @author Трапенок Виктор Викторович, Levhav@ya.ru, 89244269357
 * Буду рад новым заказам на разработку чего ни будь.
 * 
 * Levhav@ya.ru
 * Skype:Levhav
 * 89244269357
 */

 
/**
 * Внимание берёт id из $_COOKIE а не из $_SESSION
 * @return int
 */
function getUserIdOrDie()
{  
    $user_id = false;
    if(isset($_COOKIE['user_id']))
    {
        $user_id = (int)$_COOKIE['user_id'];
    }
    if(isset($_POST['user_id']))
    {
        $user_id = (int)$_POST['user_id'];
        setcookie("user_id", $user_id, time()+3600*24, '/', "comet-server.ru");  /* срок действия 1*24 час */
    }
    
    if( !$user_id )
    {
        die(json_encode(array("success"=>false, "error" => "Требуется авторизация [1]"))); 
    }
    
    $user_key = false;
    if(isset($_COOKIE['user_key']))
    {
        $user_key = $_COOKIE['user_key'];
    }
    if(isset($_POST['user_key']))
    {
        $user_key = $_POST['user_key'];
        setcookie("user_key", $user_key, time()+3600*24, '/', "comet-server.ru");  /* срок действия 1*24 час */
    }
    
    if( !$user_key )
    {
        die(json_encode(array("success"=>false, "error" => "Требуется авторизация [2]"))); 
    }
    
    
    $hashResult = "";
    $result = mysqli_query(app::conf()->getComet(), "SELECT hash FROM users_auth WHERE id = ".((int)$user_id));
    if(mysqli_errno(app::conf()->getDB()) != 0)
    {
        die ("Error code:".mysqli_errno(app::conf()->getDB())." ".mysqli_error(app::conf()->getDB())."");
    }
    else if(!mysqli_num_rows($result))
    {
        $hashResult = getUsersHash($user_id);
        mysqli_query(app::conf()->getComet(), "INSERT INTO users_auth (id, hash)VALUES (".((int)$user_id).", '".mysqli_real_escape_string(app::conf()->getComet(),$hashResult)."')"); 
        //die(json_encode(array("success"=>false, "error" => "Авторизация не пройдена [1]"))); 
    }
    else
    { 
        $row = mysqli_fetch_assoc($result);
        $hashResult = $row['hash'];
    }
    
    if($hashResult !== $user_key)
    {
        $hashResult = getUsersHash($user_id);
        if($hashResult !== $user_key)
        {
            die(json_encode(array("success"=>false, "error" => "Авторизация не пройдена [3]"))); 
        }
        mysqli_query(app::conf()->getComet(), "INSERT INTO users_auth (id, hash)VALUES (".((int)$user_id).", '".mysqli_real_escape_string(app::conf()->getComet(),$hashResult)."')"); 
    }
     
    return (int)$user_id;
}

function getAdminIdOrDie()
{
    $id = getUserIdOrDie();
    if (!in_array($id, app::conf()->admin_ids)) 
    {
        die("Требуется авторизация с правами администратора"); 
    }
    
    return $id;
}

function getUserKeyOrDie()
{
    if(isset($_COOKIE['user_key']))
    {
        return $_COOKIE['user_key'];
    }
    if(isset($_POST['user_key']))
    {
        return $_POST['user_key']; 
    }
    
    die("Требуется авторизация"); 
}


/**
 * Помечает массив сообщений прочитанным
 * @param type $from_user_id Пользователь, получатель отправитель.
 * @param type $to_user_id Пользователь, получатель сообщений.
 */
function markAsReadMessageArray($from_user_id, $to_user_id)
{ 
    // Помечаем что сообщение прочитано
    $result = mysqli_query(app::conf()->getDB(), "UPDATE `messages` SET `read_time` = '".date("U")."' WHERE to_user_id = ".$to_user_id." and from_user_id = ".$from_user_id." and read_time = 0"); 
    if( true || mysqli_affected_rows(app::conf()->getDB())) 
    {
        // Если сообщение существует и было до этого не прочитанным то отправляем уведомление.
 
        // Отправляем уведомление пользователю который отправил сообщение что сообщение прочитано
        $msg = array("to_user_id" => $to_user_id, "time" => date("U"));
        mysqli_query(app::conf()->getComet(), "INSERT INTO users_messages (id, event, message)VALUES(".$from_user_id.", 'readMessage', '".mysqli_real_escape_string(app::conf()->getComet(),json_encode($msg))."')"); 
    }
}

/**
 * Отправка уведомления в админку всем админам из конфига
 * @param array $msg
 */
function sendMsgToAdmin($event, $msg)
{  
    // Отправка уведомления в админку всем админам из конфига 
    foreach (app::conf()->admin_ids as $key => $value)
    {
        mysqli_query(app::conf()->getComet(), "INSERT INTO users_messages (id, event, message)VALUES (".$value.", '".$event."', '".$msg."')");  
    }
}