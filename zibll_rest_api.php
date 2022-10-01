<?php
/*
Plugin Name: Zibll Rest Api
Plugin URI: https://www.bxmao.net
Description: 为Zibll主题提供定制化WordPress REST API json 输出
Version: 1.0.0
Author: bxmao
Author URI: https://www.bxmao.net
WordPress requires at least: 4.7.1
*/
function ZibllApi_user_avatar($user_id)
{

    $cache = wp_cache_get($user_id, 'zibllapi_user_avatar', true);
    if (false === $cache) {
        $avatar = get_user_meta($user_id, 'custom_avatar', true);
        $avatar = $avatar ? $avatar : zib_default_avatar();
        wp_cache_set($user_id, $avatar, 'zibllapi_user_avatar');
    } else {
        $avatar = $cache;
    }
    return $avatar;
}

function postapi($data, $post, $request)
{
    $_data = $data->data;
    $postid = $post->ID;
    $thumbnai = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full')[0];
    $_data['thumbnail'] = zib_post_thumbnail('', '', true, $post);
    $_data['ziblldate'] = zib_get_time_ago($post->post_date);
    $_data['zibllcategories'] = get_the_terms($postid, 'category');
    $_data['ziblltag'] = get_the_terms($postid, 'post_tag');
    $_data['views'] = get_post_meta($postid, 'views', true);
    $_data['like'] = get_post_meta($postid, 'like', true);
    $user_id = $_data['author'];
    $usinfo = get_user_meta($user_id);
    $_data['ziblluser'] = array('userid' => $user_id, 'username' => $usinfo['nickname'][0], 'useravatar' => ZibllApi_user_avatar($user_id), 'level' => $usinfo['level'][0]);
    $data->data = $_data;
    return $data;
};
add_filter('rest_prepare_post', 'postapi', 10, 3);
function zibllcomment($request)
{
    $postid = isset($request['postid']) ? (int)$request['postid'] : 0;
    $page = isset($request['page']) ? (int)$request['page'] : 1;

    $quer = array(
        'post_id' => $postid,
        'parent' => 0,
        'number' => 10,
        'page' => $page
    );
    $comments = get_comments($quer);
    $commentslist = array();
    foreach ($comments as $comment) {
        if ($comment->comment_parent == 0) {
            $data["commentid"] = (int)$comment->comment_ID;
            $data["date"] = zib_get_time_ago($comment->comment_date);
            $data["userid"] = $comment->user_id;
            $data["username"] = $comment->comment_author;
            $data["author_url"] = ZibllApi_user_avatar($comment->user_id);
            $data["content"] = $comment->comment_content;
            $order = "asc";
            $data["child"] = getchildcomment($comment->comment_ID);
            $commentslist[] = $data;
        }
    }
    $result["code"] = "success";
    $result["message"] = "获取评论成功";
    $result["status"] = "200";
    $result["data"] = $commentslist;
    if ($cachedata == '' && function_exists('MRAC')) {
        $cachedata = MRAC()->cacheManager->set_cache($result, 'postcomments', $postid);
    }
    return $result;
}
function getchildcomment($comment_id)
{
    $quer = array(
        'parent'       => $comment_id,
        'hierarchical' => true,
        'status'       => 'approve',
    );
    $comments = get_comments($quer);

    foreach ($comments as $comment) {
        $data["commentid"] = (int)$comment->comment_ID;
        $data["date"] = zib_get_time_ago($comment->comment_date);
        $data["userid"] = $comment->user_id;
        $data["username"] = $comment->comment_author;
        $Replyname = $comment->comment_parent;
        $data["Replyname"] = get_comments(array('parent' => $Replyname))[0]->comment_author;

        $data["author_url"] = ZibllApi_user_avatar($comment->user_id);
        $data["content"] = $comment->comment_content;
        $commentslist[] = $data;
    }
    return $commentslist;
}
function zibllapi_comment()
{
    register_rest_route('zibllapi/v1', 'commentlist', ['methods' => 'get', 'callback' => 'zibllcomment']);
};
add_action('rest_api_init', 'zibllapi_comment');
function zibllapirelatedpost($request)
{
    $post = (int)$request['postid'];
    $limit = 3;
    $relatedlist = [];
    $categorys = get_the_terms($post, 'category');
    $topics    = get_the_terms($post, 'topics');
    $tags      = get_the_terms($post, 'post_tag');

    $posts_args = array(
        'showposts'           => $limit,
        'ignore_sticky_posts' => 1,
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'order'               => 'DESC',
        'tax_query'           => array(
            'relation' => 'OR',
            array(
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => array_column((array) $categorys, 'term_id'),
            ),
            array(
                'taxonomy' => 'topics',
                'field'    => 'term_id',
                'terms'    => array_column((array) $topics, 'term_id'),
            ),
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'term_id',
                'terms'    => array_column((array) $tags, 'term_id'),
            ),
        ),
    );
    $posts_args = zib_query_orderby_filter($orderby, $posts_args);
    $new_query  = new WP_Query($posts_args);
    foreach ($new_query->posts as $postinfo) {
        $relatedinfopostid = $postinfo->ID;
        $relatedinfo['postid'] = $relatedinfopostid;
        $relatedinfo['title'] = $postinfo->post_title;
        $relatedinfo['thumbnail'] = zib_post_thumbnail('', '', true, $postinfo);
        $relatedinfo["date"] = zib_get_time_ago($postinfo->post_date);
        $relatedlist[] = $relatedinfo;
    }
    return $relatedlist;
};
function zibllapi_relatedpost()
{
    register_rest_route('zibllapi/v1', 'relatedpost', ['methods' => 'get', 'callback' => 'zibllapirelatedpost']);
};
add_action('rest_api_init', 'zibllapi_relatedpost');

//user
function zibiiapimyinfo($data, $post, $request)
{
    $_data = $data->data;
    $current_user = wp_get_current_user();
    $loginid = $current_user->ID;
    $user_id = $post->ID;
    if ($loginid == $user_id) {
        $userposts = zib_get_user_post_count($user_id, 'publish');
        $usercomments = get_user_comment_count($user_id);
        $userfavorite = get_user_favorite_post_count($user_id);
        $userdata = array(
            'posts' => $userposts,
            'comments' => $usercomments,
            'favorite' => $userfavorite,

        );
        $_data['userdata'] = $userdata;
        $_data['useravatar'] = ZibllApi_user_avatar($user_id);
    }
    $data->data = $_data;
    return $data;
}
add_filter('rest_prepare_user', 'zibiiapimyinfo', 10, 3);
/*
*@1:点赞
*@2:收藏
*/
function zibllApiuseroperate($request)
{
    $current_user = wp_get_current_user();
    $loginid = $current_user->ID;
    if ($loginid == 0) {
        return array('msg' => '未登陆');
    }
    $requestdata = $request->get_body_params();
    $userid = $requestdata['userid'];
    if ($userid != $loginid) {
        return array('msg' => '非法操作');
    }
    $postid = $requestdata['postid'];
    $key = $requestdata['key'];
    $operateid = $requestdata['operateid'];

    //使用方法
    $return = $operateid == 1 ? zibllApi_posts_action('like-posts', $postid, $key, '已赞！感谢您的支持', '点赞已取消') : zibllApi_posts_action('favorite-posts', $postid, $key, '已收藏此文章', '已取消收藏');
    return $return;
}
function ZibllApi_user_operate()
{
    register_rest_route('zibllapi/v1', 'useroperate', ['methods' => 'post', 'callback' => 'zibllApiuseroperate']);
};
add_action('rest_api_init', 'ZibllApi_user_operate');

function zibllapipostcomment($request)
{
    $current_user = wp_get_current_user();
    $loginid = $current_user->ID;
    if ($loginid == 0) {
        return array('msg' => '未登陆');
    }
    $userid = $request['userid'];
    if ($userid != $loginid) {
        return array('msg' => '非法操作');
    }
    $comment = sanitize_textarea_field($request['comment']);
    $postid = $request['postid'];
    $commentid = $request['commentid'];

    $array = array(
        'comment' => $comment,
        'comment_post_ID' => $postid,
        'comment_parent' => $commentid,
        'action' => 'submit_comment'
    );
    // return $array;
    //内容合规性判断
    $is_audit = false;
    if (zib_current_user_can('comment_audit_no', (!empty($postid) ? $postid : 0))) {
        //拥有免审核权限
        $is_audit = true;
    } else {
        //API审核（拥有免审核权限的用户无需API审核）
        if (_pz('audit_comment')) {
            $api_is_audit = ZibAudit::is_audit(ZibAudit::ajax_text(zib_comment_filters($comment)));
            //API审核通过，且拥有免人工审核
            if ($api_is_audit && zib_current_user_can('comment_audit_no_manual')) {
                $is_audit = true;
            }
        }
    }

    if ($is_audit) {
        add_filter('pre_comment_approved', function () {
            return 1;
        });
    }
    $comment = wp_handle_comment_submission(wp_unslash($array));
    if (is_wp_error($comment)) {
        $data = $comment->get_error_data();
        if (!empty($data)) {
            return array(
                'code' => 2,
                'msg' => $comment->get_error_message()
            );
        } else {
            return array(
                'code' => 3,
                'msg' => '评论提交失败'
            );
        }
    }
    return array(
        'code' => 1,
        'msg' => '评论成功'
    );
}


function ZibllApi_postcomment()
{
    register_rest_route('zibllapi/v1', 'postcomment', ['methods' => 'post', 'callback' => 'zibllapipostcomment']);
};
add_action('rest_api_init', 'ZibllApi_postcomment');



/**
 * @param string $user_meta_name 用户元数据字段
 * @param string $post_id 文章id
 * @param string $key 操作类型
 * @param string $add_msg 增加提示
 * @param string $rem_msg 取消提示
 * @param bool $is_comment 是否是帖子
 * @return string
 */
function zibllApi_posts_action($user_meta_name, $post_id, $key, $add_msg = '已完成', $rem_msg = '已取消')
{
    $user_meta = false;
    $is_in_meta = false;
    $user_id = get_current_user_id();

    if ($user_id) {
        $user_meta = get_user_meta($user_id, $user_meta_name, true);
        if ($user_meta) {
            $user_meta = maybe_unserialize($user_meta);
            $is_in_meta = in_array($post_id, $user_meta);
        }
    }
    if (!$user_meta || !$is_in_meta) {
        if (!$user_meta) {
            $user_meta = array($post_id);
        } else {
            array_unshift($user_meta, $post_id);
        }
        action_update_meta($user_meta_name, $user_meta);
        $g = (int) get_post_meta($post_id, $key, true);
        if (!$g) {
            $g = 0;
        }
        $count = $g + 1;
        $count = $count < 1 ? 0 : $count;
        update_post_meta($post_id, $key, $count);
        //添加处理挂钩
        do_action($user_meta_name, $post_id, $count, $user_id);
        return array(
            'msg' => $add_msg,
        );
    }
    if ($is_in_meta) {
        $h = array_search($post_id, $user_meta);
        unset($user_meta[$h]);
        action_update_meta($user_meta_name, $user_meta);
        if ($is_comment) {
            $g = (int) get_comment_meta($post_id, $key, true);
        } else {
            $g = (int) get_post_meta($post_id, $key, true);
        }
        $count = $g - 1;
        $count = $count < 1 ? 0 : $count;

        if ($is_comment) {
            update_comment_meta($post_id, $key, $count);
        } else {
            update_post_meta($post_id, $key, $count);
        }
        return array(
            'msg' => $rem_msg,
        );
    }
}

