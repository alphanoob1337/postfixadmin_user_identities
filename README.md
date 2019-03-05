# postfixadmin_user_identities
Roundcubemail plugin to have identities managed by postfixAdmin.

# Installation and usage
Unpack the files into `plugins/postfixadmin_user_identities`. Add `'postfixadmin_user_identities'` to your plugin array in your `config/config.inc.php`. I.e. like this:

    $config['plugins'] = array('postfixadmin_user_identities');

Then you can create your own configuration file specifiying the database connection as well as the structure of the table. Since the latter should be the same for every recent postfixAdmin setup, you probably only need to specify the database. So a very simple configuration file located under `plugins/postfixadmin_user_identities/config.inc.php` could look like this:

    <?php
    
    // ----------------------------------
    // SQL DATABASE
    // ----------------------------------
    // Similar to the $config['db_dsnw'] config variable, this is a
    // database connection string (DSN) for read operations.
    // For examples see http://pear.php.net/manual/en/package.database.mdb2.intro-dsn.php
    $config['postfixadmin_user_identities_db_dsnr'] = 'mysql://<postfix-db-user>:<postfix-db-pass>@<postfix-db-hostname>/<postfix-db-name>';

All other available configuration parameters can be found in `plugins/postfixadmin_user_identities/config.inc.php.dist`.

**ENJOY**
