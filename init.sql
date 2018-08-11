UPDATE mysql.user
    set authentication_string=PASSWORD("nbjes01."),
    password_expired='N',
    plugin='mysql_native_password'
where User='root';
 
flush privileges;
