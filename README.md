php-mysql
=========

MySQL ORM interface for PHP.

## Example

     class UsersModel
     {
	    function print_name()
	    {
		    echo $this->first_name . ' ' . $this->last_name;
	    }
     }
     
     $users = $db->query('SELECT * FROM `users` WHERE first_name = ?', 'Alan');
     $users[0]->print_name();
