<?php
    class UserController {

        protected $layout;

        public function __construct(){
            $this->layout = array('header' => "user/layout/header", 'footer' => "user/layout/footer");
            $this->layout["vars"] = array();
            $this->layout["vars"]["categories"] = DB::query("SELECT id, title FROM category");
        }

        /* This method is used to display the view for login */

        public function indexLogin(){
        	return View::makeWithLayout('user/login/login', $this->layout);
        }

        /* This method is used to check the credentials and login the user */

        public function login(){
        	$allInput = Input::all("POST");
            $result = Auth::login($allInput);
            if($result['success']){
                return Redirect::to('/');
            }
            else {
                return View::makeWithLayout('/user/login/login', $this->layout, array('message' => $result['message'], 'input' => $allInput));
            }
        }

        /* This method is used to display the view for register */

        public function indexRegister(){
            return View::makeWithLayout('user/login/register', $this->layout);
        }

        /* This method is used to save user credentials in database */

        public function register(){
            $allInput = Input::all("POST");
            $result = Auth::register($allInput);
            if($result['success']){
                Auth::login($allInput);
                return Redirect::to('/');
            }
            else {
                return View::makeWithLayout('/user/login/register', $this->layout, array('message' => $result['message'], 'input' => $allInput));
            }
        }

        /* This method is used to unset the session of the user */
        
        public function logout(){
            Auth::logout();
            return Redirect::to('/');
        }

        /* This method is used to display the view for the user profile based on id */

        public function profileIndex(){
            $id = Input::get('id');
            $user = DB::query("SELECT id,first_name,last_name,email,gender,avatar,phone FROM user WHERE id = '$id'")[0];
            $user['badges'] = DB::query("SELECT badge.title, badge.description FROM user_badge LEFT JOIN badge ON badge.id = user_badge.badge_id WHERE user_badge.user_id = '$id'");
            $user['interests'] = DB::query("SELECT interest.title FROM user_interest LEFT JOIN interest ON interest.id = user_interest.interest_id WHERE user_interest.user_id = '$id'");
            $user['question_count'] = DB::query("SELECT COUNT(*) as count FROM question WHERE user_id = '$id'")[0]['count'];
            $user['answer_count'] = DB::query("SELECT COUNT(*) as count FROM answer WHERE user_id = '$id'")[0]['count'];

            return View::makeWithLayout('/user/profile/index', $this->layout, array('user' => $user));
        }

        /* This method is used to save the avatar file through ajax */

        public function saveAvatar(){
            $file = $_FILES['avatar'];
            $target_dir = "public/img/avatar/";
            $file_name = $this->generateRandomString(30) . "." . pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION);;
            $target_file = $target_dir . $file_name;
        
            move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file);

            $user_id = Input::post('user_id');
            DB::query("UPDATE user SET avatar = '$file_name' WHERE id = '$user_id'", "update");

            echo $file_name;
            return;
        }

        /* This method is used to display the view used to edit user profile */

        public function editProfileIndex(){
            $id = Input::get('id');
            if($id != Auth::getUserId()){
                return Redirect::to('/');
            }
            else {
                $user = DB::query("SELECT id,first_name,last_name,email,avatar,phone FROM user WHERE id = '$id'")[0];
                $user['interests'] = array();
                $interestsIds = DB::query("SELECT interest.id FROM user_interest LEFT JOIN interest ON interest.id = user_interest.interest_id WHERE user_interest.user_id = '$id'");
                foreach($interestsIds as $id){
                    $user['interests'][] = $id['id'];
                }
                $interests = DB::query("SELECT * FROM interest");

                return View::makeWithLayout('/user/profile/edit', $this->layout, array('input' => $user, 'interests' => $interests));
            }
        }

        /* This method is used to save the informations about the user */

        public function editProfile(){
            $input = Input::all('POST');
            if($input['first_name'] != "" && $input['last_name'] != "" && $input['phone'] != ""){
                $user_id = Auth::getUserId();
                DB::query("UPDATE `user` SET `first_name` = '".$input['first_name']."', `last_name` = '".$input['last_name']."', `phone` = '".$input['phone']."' WHERE id = '$user_id'", "update");

                DB::query("DELETE FROM user_interest WHERE user_id = '$user_id'", "delete");

                foreach($input['interests'] as $id){
                    DB::query("INSERT INTO user_interest VALUES('$user_id', '$id')", "insert");
                }

                return Redirect::to('/profile?id=' . $user_id);
            }
            else {
                $interests = DB::query("SELECT * FROM interest");
                return View::makeWithLayout('/user/profile/edit', $this->layout, array('message' => 'All fields are required.', 'input' => $input, 'interests' => $interests));
            }
        }

        /* This method is used to generate a random string */

        public function generateRandomString($length = 10) {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            return $randomString;
        }

        /* This method is used to display the view with the users list */

        public function usersList(){
            $users = DB::query("SELECT id,avatar,first_name,last_name FROM user");
            return View::makeWithLayout('/user/users/index', $this->layout, array('users' => $users));
        }

        /* This method is used to display the view used for forgot password */

        public function forgotIndex(){
            return View::makeWithLayout('/user/login/forgot', $this->layout);
        }

        /* This method is used to generate a token and to send an email to the user with reset password link */

        public function forgot(){
            $token = $this->generateRandomString(20);
            $email = Input::post('email');

            $count = DB::query("SELECT COUNT(*) AS count FROM user WHERE email = '$email'")[0]['count'];
            if($count == 1){
                DB::query("UPDATE user SET token = '$token' WHERE email = '$email'", "update");
                $url = URL::getTo('/reset?token=' . $token);
                $message = "Access the following link to reset your password " . $url;
                Mail::send($email, $message);
                return Redirect::to('/');
            }
            else {
                return View::makeWithLayout('/user/login/forgot', $this->layout, array("message" => "The email is not registered."));
            }
        }

        /* This method is used to display the view used for reset password */

        public function resetIndex(){
            $token = Input::get('token');
            return View::makeWithLayout('/user/login/reset', $this->layout, array("token" => $token));
        }

        /* This method is used to change the password of the user */

        public function reset(){
            $token = Input::get('token');
            $user_id = DB::query("SELECT id FROM user WHERE token = '$token'")[0]['id'];
            if($user_id != null){
                $password = Input::post('password');
                $password2 = Input::post('password2');
                if($password == $password2){
                    $password = md5($password);
                    DB::query("UPDATE user SET password = '$password', token = '' WHERE id = '$user_id'", "update");
                    return Redirect::to('/');
                }
                else {
                    return View::makeWithLayout('/user/login/reset', $this->layout, array("token" => $token, "message" => "The passwords do not match."));
                }
            }
            else {
                return Redirect::to('/');
            }
        }

    }
 ?>
