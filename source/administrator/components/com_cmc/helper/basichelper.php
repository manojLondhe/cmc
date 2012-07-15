<?php
/**
 * Cmc
 * @package Joomla!
 * @Copyright (C) 2012 - Yves Hoppe - compojoom.com
 * @All rights reserved
 * @Joomla! is Free Software
 * @Released under GNU/GPL License : http://www.gnu.org/copyleft/gpl.html
 * @version $Revision: 1.0.0 stable $
 **/


defined('_JEXEC') or die('Restricted access');

class CmcHelper {

    /**
     * @static
     * @return bool
     */
    public static function checkRequiredSettings()
    {
        $api_key = CmcSettingsHelper::getSettings("api_key", '');
        $webhook = CmcSettingsHelper::getSettings("webhook_secret", '');

        if(!empty($api_key) && !empty($webhook)){
            return true;
        }

        return false;
    }


    /**
     * @static
     * @param $list_id
     */
    public static function getListName($list_id){
       return($list_id);
    }

    /**
     * @return mixed
     */

    public static  function getLists() {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('*')
            ->from('#__cmc_lists');
        $db->setQuery($query);
        return $db->loadObjectList();
    }

    /**
     * @static
     * @param $api_key
     * @param $list_id
     * @param $email
     * @return string
     */
    public static function getUserDetailsMC($api_key, $list_id, $email, $id = null, $store = true){
        $api = new MCAPI($api_key);

        $retval = $api->listMemberInfo( $list_id, $email);

        if ($api->errorCode){
            return(JError::raiseError(JTEXT::_("COM_CMC_LOAD_USER_FAILED")) . " " .$api->errorCode . " / " . $api->errorMessage);
        } else {
//            echo "Success:".$retval['success']."\n";
//            echo "Errors:".sizeof($retval['error'])."\n";
//            //below is stupid code specific to what is returned
//            //Don't actually do something like this.
//            $i = 0;
//            foreach($retval['data'] as $k=>$v){
//                echo 'Member #'.(++$i)."\n";
//                if (is_array($v)){
//                    //handle the merges
//                    foreach($v as $l=>$w){
//                        if (is_array($w)){
//                            echo "\t$l:\n";
//                            foreach($w as $m=>$x){
//                                echo "\t\t$m = $x\n";
//                            }
//                        } else {
//                            echo "\t$l = $w\n";
//                        }
//                    }
//                } else {
//                    echo "$k = $v\n";
//                }
//            }
            /**
             *  @return array array of list members with their info in an array (see Returned Fields for details)
             *  @returnf int success the number of subscribers successfully found on the list
             *  @returnf int errors the number of subscribers who were not found on the list
             *  @returnf array data an array of arrays where each one has member info:
                string id The unique id for this email address on an account
                string email The email address associated with this record
                string email_type The type of emails this customer asked to get: html, text, or mobile
                array merges An associative array of all the merge tags and the data for those tags for this email address.
             * <em>Note</em>: Interest Groups are returned as comma delimited strings - if a group name contains a comma,
             * it will be escaped with a backslash. ie, "," =&gt; "\,". Groupings will be returned with their "id" and "name"
             * as well as a "groups" field formatted just like Interest Groups
                string status The subscription status for this email address, either pending, subscribed, unsubscribed, or cleaned
                string ip_opt IP Address this address opted in from.
                string ip_signup IP Address this address signed up from.
                int member_rating the rating of the subscriber. This will be 1 - 5 as described <a href="http://eepurl.com/f-2P" target="_blank">here</a>
                string campaign_id If the user is unsubscribed and they unsubscribed from a specific campaign, that campaign_id will be listed, otherwise this is not returned.
                array lists An associative array of the other lists this member belongs to - the key is the list id and the value is their status in that list.
                date timestamp The time this email address was added to the list
                date info_changed The last time this record was changed. If the record is old enough, this may be blank.
                int web_id The Member id used in our web app, allows you to create a link directly to it
                array clients the various clients we've tracked the address as using - each included array includes client 'name' and 'icon_url'
                array static_segments the 'id', 'name', and date 'added' for any static segment this member is in
             */

            $item = array();

            foreach($retval['data'] as $user) {
                $item['id'] = $id;
                $item['list_id'] = $list_id;
                $item['email_type'] = $user['email_type'];
                $item['email'] = $user['email'];

                $item['merges'] = json_encode($user['merges']);

                $item['firstname'] = $user['merges']['FNAME'];
                $item['lastname'] = $user['merges']['LNAME'];

                //$item['interests'] = $user['merges']['INTERESTS'];

                $item['status'] = $user['status'];;
                $item['ip_opt'] = $user['ip_opt'];
                $item['ip_signup'] = $user['ip_signup'];

                $item['member_rating'] = $user['member_rating'];

                $item['timestamp'] = $user['timestamp'];
                $item['info_changed'] = $user['info_changed'];

                $item['web_id'] = $user['web_id'];
                $item['clients'] = json_encode($user['clients']);
                $item['static_segments'] = json_encode($user['static_segments']);

                $item['lists'] = json_encode($user['lists']);


                $item['query_data'] = json_encode($retval);

                if($store){
                    $row = JTable::getInstance('users', 'CmcTable');

                    if (!$row->bind($item)) {
                        return JError::raiseError(JText::_('COM_CMC_LIST_ERROR_SAVING') . " " . $row->getErrorMsg());
                    }

                    if (!$row->check()) {
                        return JError::raiseError(JText::_('COM_CMC_LIST_ERROR_SAVING') . " " . $row->getErrorMsg());
                    }

                    if (!$row->store()) {
                        return JError::raiseError(JText::_('COM_CMC_LIST_ERROR_SAVING') . " " . $row->getErrorMsg());
                    }
                }
            }

            return $row;
        }

    }

    /**
     * @static
     * @param $api_key
     * @param $list_id
     * @param $email
     * @param $firstname
     * @param $lastname
     * @param null $user
     * @param array $groupings
     */
    public static function subscribeList($api_key, $list_id, $email, $firstname, $lastname, $user = null, $groupings = array(null)){

        $api = new MCAPI($api_key);

        $merge_vars = array('FNAME'=>$firstname, 'LNAME'=>$lastname,
            $groupings
        );

        // By default this sends a confirmation email - you will not see new members
        // until the link contained in it is clicked!
        $retval = $api->listSubscribe( $list_id, $email, $merge_vars );

        if ($api->errorCode){
            return(JError::raiseError(JTEXT::_("COM_CMC_SUBSCRIBE_FAILED")) . " " .$api->errorCode . " / " . $api->errorMessage);
        } else {
            return true;
        }
    }

    /**
     * @static
     * @param $api_key
     * @param $list_id
     * @param $email
     * @param null $user
     * @return bool|string
     */
    public static function unsubscribeList($api_key, $list_id, $email, $user = null){
        $api = new MCAPI($api_key);

        $retval = $api->listUnsubscribe( $list_id, $email);
        if ($api->errorCode){
            return(JError::raiseError(JTEXT::_("COM_CMC_UNSUBSCRIBE_FAILED")) . " " .$api->errorCode . " / " . $api->errorMessage);
        } else {
            return true;
        }
    }


    /**
     * @static
     * @param $api_key
     * @param $list_id
     * @param $email
     * @param null $firstname
     * @param null $lastname
     * @param string $email_type
     * @param null $user
     * @return bool|string
     */
    public static function updateUser($api_key, $list_id, $email, $firstname=null, $lastname =null, $email_type="html", $user=null){
        $api = new MCAPI($api_key);

        $merge_vars = array("FNAME"=>$firstname, "LNAME"=>$lastname);

        $retval = $api->listUpdateMember($list_id, $email, $merge_vars, $email_type, false);

        if ($api->errorCode){
            return(JError::raiseError(JTEXT::_("COM_CMC_UNSUBSCRIBE_FAILED")) . " " .$api->errorCode . " / " . $api->errorMessage);
        } else {
            return true;
        }
    }

    /**
     * @static
     * @param $api_key
     * @param $list_id
     * @param bool $optin
     * @param bool $up_exist
     * @param bool $replace_int
     * @return string
     */
    public static function subscribeListBatch($api_key, $list_id, $batchlist, $optin = true, $up_exist=true, $replace_int = false){
        $api = new MCAPI($api_key);

//        $batch[] = array('EMAIL'=>$my_email, 'FNAME'=>'Joe');
//        $batch[] = array('EMAIL'=>$boss_man_email, 'FNAME'=>'Me', 'LNAME'=>'Chimp');

        // Todo check rights

        $optin = true; //yes, send optin emails
        $up_exist = true; // yes, update currently subscribed users
        $replace_int = false; // no, add interest, don't replace

        $vals = $api->listBatchSubscribe($list_id, $batch, $optin, $up_exist, $replace_int);

        if ($api->errorCode){
            return(JError::raiseError(JTEXT::_("COM_CMC_UNSUBSCRIBE_FAILED")) . " " .$api->errorCode . " / " . $api->errorMessage);
        } else {
            // Todo return this
            echo "added:   ".$vals['add_count']."\n";
            echo "updated: ".$vals['update_count']."\n";
            echo "errors:  ".$vals['error_count']."\n";
            foreach($vals['errors'] as $val){
                echo $val['email_address']. " failed\n";
                echo "code:".$val['code']."\n";
                echo "msg :".$val['message']."\n";
            }
        }

    }



}