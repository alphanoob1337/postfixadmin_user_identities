<?php
/**
 * postfixAdmin managed user identities
 *
 * This plugin requires that a postfixAdmin database is configured.
 *
 * @author alphanoob1337
 * @license MIT
 */
class postfixadmin_user_identities extends rcube_plugin
{
    public $task = 'login';

    private $rc;
    private $db;

    function init()
    {
        $this->rc = rcmail::get_instance();

        $this->add_hook('user_create', array($this, 'fetch_identities'));
        $this->add_hook('login_after', array($this, 'recheck_identities'));
    }

    function fetch_identities($args)
    {
        
        $this->load_config('config.inc.php.dist');
        $this->load_config('config.inc.php');

        // Connect to database
        $this->db = rcube_db::factory($this->rc->config->get('postfixadmin_user_identities_db_dsnr'), '', false);
        $this->db->db_connect('w');

        // Get full name and main identity
        $t_mbox = $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_table_mbox'));
        $c_mbox_uid = $t_mbox . '.' . $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_col_uname'));
        $c_mbox_fname = $t_mbox . '.' . $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_col_fullname'));
        $c_mbox_email = $t_mbox . '.' . $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_col_email'));
        $c_mbox_dom = $t_mbox . '.' . $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_col_mbox_domain'));

        $qrystr = "SELECT " .
            $c_mbox_fname . ", " .
            $c_mbox_email . ", " .
            $c_mbox_dom .
            " FROM " . $t_mbox .
            " WHERE " . $c_mbox_uid . " = '" . $this->db->escape($args['user']) . "'";

        $qh = $this->db->query($qrystr);
        $result = $this->db->fetch_array($qh);
        if ($result !== FALSE)
        {
            $hidden_domains = $this->rc->config->get('postfixadmin_user_identities_hide_domains');

            // Set full user name
            $args['user_name'] = $result[0];
            
            $email_list = array();
            
            $email_list[] = array($result[1], $result[2]);
            
            // Fetch alias domains
            $t_adom = $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_table_aliasdomain'));
            $c_adom_source = $t_adom . '.' . $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_col_aliasdomain'));
            $c_adom_destin = $t_adom . '.' . $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_col_gotodomain'));
            $c_adom_active = $t_adom . '.' . $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_col_aliasdomain_active'));

            $t_dom = $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_table_domain'));
            $c_dom_dom = $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_col_domain'));
            $c_dom_active = $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_col_domain_active'));

            $sdom = $this->db->quote_identifier('srcdom');
            $ddom = $this->db->quote_identifier('dstdom');

            $qrystr = "SELECT " .
                $c_adom_source . ", " .
                $c_adom_destin .
                " FROM " . $t_adom .
                " LEFT OUTER JOIN " . $t_dom . " AS " . $sdom . " ON " . $c_adom_source . " = " . $sdom . "." . $c_dom_dom .
                " LEFT OUTER JOIN " . $t_dom . " AS " . $ddom . " ON " . $c_adom_destin . " = " . $ddom . "." . $c_dom_dom .
                " WHERE " . $c_adom_active . " = 1 AND" .
                " " . $sdom . "." . $c_dom_active . " = 1 AND" .
                " " . $ddom . "." . $c_dom_active . " = 1";

            $adom_map = array();

            $qh = $this->db->query($qrystr);
            $result = $this->db->fetch_array($qh);
            while ($result !== FALSE)
            {
                $adom_map[] = array($result[1], $result[0]);
                $result = $this->db->fetch_array($qh);
            }

            // Apply alias domains for the first time
            $maxrecursion = count($adom_map);
            foreach ($email_list AS $list_item)
            {
                $email = $list_item[0];
                $domain = $list_item[1];

                $adom_map2 = $adom_map;
                for ($i = 0; $i < $maxrecursion; $i++)
                {
                    foreach ($adom_map2 AS $k => $alias)
                    {
                        if ($domain == $alias[0])
                        {
                            $newdomain = $alias[1];
                            $newemail = substr($email, 0, -1*strlen($domain)) . $newdomain;

                            if (!in_array(array($newemail, $newdomain), $email_list))
                                $email_list[] = array($newemail, $newdomain);
                            
                            unset($adom_map2[$k]);
                            break;
                        }
                    }
                }
            }

            // Fetch address aliases
            $t_a = $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_table_alias'));
            $c_a_source = $t_a . '.' . $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_col_address'));
            $c_a_destin = $t_a . '.' . $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_col_goto'));
            $c_a_active = $t_a . '.' . $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_col_alias_active'));
            $c_a_dom = $t_a . '.' . $this->db->quote_identifier($this->rc->config->get('postfixadmin_user_identities_col_alias_domain'));

            $qrystr = "SELECT " .
                $c_a_source . ", " .
                $c_a_destin .
                " FROM " . $t_a .
                " LEFT OUTER JOIN " . $t_dom . " ON " . $c_a_dom . " = " . $t_dom . "." . $c_dom_dom .
                " WHERE " . $c_a_active . " = 1 AND" .
                " " . $t_dom . "." . $c_dom_active . " = 1";

            $alias_map = array();

            $qh = $this->db->query($qrystr);
            $result = $this->db->fetch_array($qh);
            while ($result !== FALSE)
            {
                foreach(explode(',', $result[1]) AS $dest)
                    $alias_map[] = array($dest, $result[0]);
                $result = $this->db->fetch_array($qh);
            }

            // Apply aliases
            $maxrecursion = count($alias_map);
            foreach ($email_list AS $list_item)
            {
                $email = $list_item[0];
                $domain = $list_item[1];

                $alias_map2 = $alias_map;
                for ($i = 0; $i < $maxrecursion; $i++)
                {
                    foreach ($alias_map2 AS $k => $alias)
                    {
                        if ($email == $alias[0])
                        {
                            $newemail = $alias[1];
                            $newdomain = strrev(explode('@', strrev($newemail), 2)[0]);

                            if (!in_array(array($newemail, $newdomain), $email_list))
                                $email_list[] = array($newemail, $newdomain);
                            
                            unset($alias_map2[$k]);
                            break;
                        }
                    }
                }
            }
            
            // Apply alias domains again
            $maxrecursion = count($adom_map);
            foreach ($email_list AS $list_item)
            {
                $email = $list_item[0];
                $domain = $list_item[1];

                $adom_map2 = $adom_map;
                for ($i = 0; $i < $maxrecursion; $i++)
                {
                    foreach ($adom_map2 AS $k => $alias)
                    {
                        if ($domain == $alias[0])
                        {
                            $newdomain = $alias[1];
                            $newemail = substr($email, 0, -1*strlen($domain)) . $newdomain;

                            if (!in_array(array($newemail, $newdomain), $email_list))
                                $email_list[] = array($newemail, $newdomain);
                            
                            unset($adom_map2[$k]);
                            break;
                        }
                    }
                }
            }

            // Filter results
            $f_email_list = array();
            foreach ($email_list AS $list_item)
            {
                $email = $list_item[0];
                $domain = $list_item[1];
                if (!in_array($domain, $hidden_domains))
                {
                    $f_email_list[] = $email;
                }
            }
            $email_list = $f_email_list;
            
            if (count($email_list) == 1)
                $args['user_email'] = $email_list[0];
            
            if (count($email_list) >= 1)
                $args['email_list'] = $email_list;
        }

        $this->db->closeConnection();

        return $args;
    }

    function recheck_identities($args)
    {
        $this->load_config('config.inc.php.dist');
        $this->load_config('config.inc.php');

        // Fetch the existing e-mail addresses of the user
        $old_identities = $this->rc->user->list_identities();

        // Fetch the database
        $db_data = $this->fetch_identities(
            array(
                'user' => $this->rc->user->data['username'],
                'host' => $this->rc->user->data['mail_host'],
            ));
        
        // Add new identities
        foreach ($db_data['email_list'] as $email)
        {
            $already_existing = FALSE;
            foreach ($old_identities as $identity)
            {
                if ($identity['email'] == $email) {
                    $already_existing = TRUE;
                    break;
                }
            }

            if (!$already_existing)
            {
                $plugin = $this->rc->plugins->exec_hook('identity_create',
                    array(
                        'login'  => true,
                        'record' => array(
                            'user_id'  => $this->rc->user->ID,
                            'standard' => 0,
                            'email'    => $email,
                            'name'     => $db_data['user_name']
                        )
                    ));

                if (!$plugin['abort'] && $plugin['record']['email']) {
                    $this->rc->user->insert_identity($plugin['record']);
                }
            }
        }

        // Remove missing identities
        foreach ($old_identities as $identity)
        {
            $missing = TRUE;
            foreach ($db_data['email_list'] as $email)
            {
                if ($identity['email'] == $email) {
                    $missing = FALSE;
                    break;
                }
            }

            if ($missing)
            {
                $plugin = $this->rc->plugins->exec_hook('identity_delete',
                    array(
                        'id' => $identity['identity_id']
                    ));

                if (!$plugin['abort']) {
                    $this->rc->user->delete_identity($plugin['id']);
                }
            }
        }

        return $args;
    }
}