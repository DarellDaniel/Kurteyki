<?php if (!defined('BASEPATH')) {exit('No direct script access allowed');}

class M_Blog_Post extends CI_Model
{

    public $table_blog_post = 'tb_blog_post';
    public $table_blog_post_category = 'tb_blog_post_category';
    public $table_blog_post_tags = 'tb_blog_post_tags';
    public $table_blog_post_comment = 'tb_blog_post_comment';

    public function datatables(){
        return [
            'datatable' => true,
            'datatables_data' => "
            [{'data': 'checkbox',className:'c-table__cell u-pl-small'},
            {'data': 'id',className:'c-table__cell'},
            {'data': 'title',className:'c-table__cell u-pl-small',width:'100%'},
            {'data': 'category',className:'c-table__cell u-text-center'},
            {'data': 'time',className:'c-table__cell'},            
            {'data': 'updated',className:'c-table__cell'},                                                          
            {'data': 'alat',className:'c-table__cell'} ]
            ",
        ];
    }

    public function data_table(){

        header('Content-Type: application/json');        

        /** disable because long process **/
        // GROUP_CONCAT(DISTINCT tb_blog_post_tags.name ORDER BY tb_blog_post_tags.name ASC) as bpt_name,   
        // $this->datatables->join($this->table_blog_post_tags, 'FIND_IN_SET(tb_blog_post_tags.id,tb_blog_post.id_tags) > 0', 'LEFT OUTER');

        $this->datatables->select('
            tb_blog_post.id,
            tb_blog_post.title,
            DATE_FORMAT(tb_blog_post.time, "%d %M %Y %H:%i %p") as time,
            tb_blog_post.time as timeorigin,
            DATE_FORMAT(tb_blog_post.updated, "%d %M %Y %H:%i %p") as updated,            
            tb_blog_post.permalink,
            tb_blog_post.views,
            tb_blog_post.status,
            tb_blog_post_category.name as category,
            GROUP_CONCAT(DISTINCT tb_blog_post_comment.id) as comments,  
            ');
        $this->datatables->from($this->table_blog_post);
        $this->datatables->join($this->table_blog_post_category, 'tb_blog_post.id_category = tb_blog_post_category.id', 'LEFT');
        $this->datatables->join($this->table_blog_post_comment, 'tb_blog_post_comment.id_blog_post = tb_blog_post.id', 'LEFT');
        $this->datatables->group_by('tb_blog_post.id');
        $this->datatables->add_column('checkbox', '
            <td>
            <div class="c-choice c-choice--checkbox">
            <input type="checkbox" id="checkbox-$1" class="c-choice__input" name="id[]" value="$1">
            <label for="checkbox-$1" class="c-choice__label">&nbsp;</label>
            </div>
            </td>
            ', 'id');

        $this->datatables->edit_column('title', '
            <a title="$1" href="' . base_url('post/') ."$2" . '" target="_blank">$1</a>
            <span class="u-block u-text-mute">
            <small class="u-mr-xsmall">$3</small>
            <small class="u-mr-xsmall"><i class="fa fa-eye u-color-warning"></i>&nbsp; $4</small>
            <small class="u-mr-xsmall"><i class="fa fa-comment u-color-info"></i>&nbsp; $5</small>            
            </span>
            ', 'ctsubstr(title,60),permalink,formatstatus(timeorigin,status),views,countcomment(comments)');
        $this->datatables->edit_column('category', '$1', 'ucwords(category)');

        $this->datatables->add_column('alat', '
            <button type="button" class="c-btn--custom c-btn--small c-btn c-btn--primary" name="action-view"><i class="fa fa-eye"></i></button>
            <a class="c-btn--custom c-btn--small c-btn c-btn--info" href="'.base_url('app/blog_post/').'update/$1"><i class="fa fa-edit"></i></a>
            <button type="button" data-title="are you sure ?" data-text="want to delete $2" class="c-btn c-btn--danger c-btn--custom action-delete" data-href="'. base_url('app/blog_post/delete/$1') .'">
            <i class="fa fa-trash"></i>
            </button>
            ', 'id,title');

        return $this->datatables->generate();
    }

    public function required($withpost = false){

        $data = [
            'categorys' => $this->_Process_MYSQL->read_data($this->table_blog_post_category, 'id', 'DESC')->result_array(),
            'tags' => $this->_Process_MYSQL->read_data($this->table_blog_post_tags, 'id', 'DESC')->result_array(),
        ];

        if ($withpost) {

            $post = $this->db
            ->select("id,title")
            ->from($this->table_blog_post)
            ->where("time <= NOW()")
            ->where("status = 'Published'")
            ->get()->result_array();

            $data = array_merge($data,['post' => $post]);
        }

        return $data;
    }

    public function data_post(){

        $post_data = [
            'title' => htmlentities($this->input->post('title')),
            'image' => htmlentities($this->input->post('image')),
            'permalink' => $this->input->post('permalink'),
            'time' => htmlentities($this->input->post('time')),
            'id_category' => $this->input->post('id_category'),
            'id_tags' => $this->input->post('id_tags'),
            'content' => htmlentities($this->input->post('content')),
            'description' => htmlentities($this->input->post('description')),                        
            'status' => strip_tags($this->input->post('status')),            
        ];

        /**
         * Check if process update > merge updated data
         */
        if (!empty($this->input->post('id'))) {

            $post_merge = array(
                'updated' => date('Y-m-d H:i:s'),
            );

            $post_data = array_merge($post_data, $post_merge);   
        }     


        /**
         * Set Permalink News if Update
         */
        if (empty($post_data['permalink'])) {
            $permalink_news = strtolower(slug($post_data['title']));
        }else {        
            $permalink_old = strtolower(slug($this->input->post('permalink'."_old")));
            $set_permalink = strtolower(slug($post_data['permalink']));

            if ($permalink_old == $set_permalink) {
                $permalink_news = $set_permalink;                
            }else {

                $read_post = $this->_Process_MYSQL->get_data($this->table_blog_post, array('permalink' => $set_permalink))->num_rows();
                if ($read_post > 0) {
                    $uniqe_string = rand();
                    $permalink_news = $set_permalink."-".$uniqe_string;
                }else {
                    $permalink_news = $set_permalink;
                }
            }
        }

        $post_data['permalink'] = $permalink_news;         


        /**
         * Check if Have new category
         */
        $category = strtolower($post_data['id_category']);
        $read_category = $this->_Process_MYSQL->get_data($this->table_blog_post_category, array('id' => $category));

        $read_bpc = $read_category->row();
        if ($read_category->num_rows() > 0) {

            $category_news = $category;
        } else {

            $data_category = array(
                'name' => $category,
                'slug' => slug($category),
            );

            if ($this->_Process_MYSQL->insert_data($this->table_blog_post_category, $data_category)) {

                $read_category = $this->_Process_MYSQL->get_data($this->table_blog_post_category, array('name' => $category))->row();

                $category_news = $read_category->id;

            } else {
                # failed insert new category
            }
        }

        $post_data['id_category'] = $category_news; 


        /**
         * Process check new tags
         */
        $tags_post = array_map('strtolower', $post_data['id_tags']);
        $read_tags = $this->_Process_MYSQL->get_data_multiple($this->table_blog_post_tags, $tags_post, 'id', 'name');

        # read data and set to array data
        foreach ($read_tags->result() as $tag) {
            $tags[] = $tag->id;
            $tags[] = $tag->name;
            $tags_insert[] = $tag->id;
        }


        # check if tags exist, remove same value and get new tags
        if (!empty($tags)) {
            $tags_news = array_diff(array_unique($tags_post), $tags);
        } else {
            $tags_news = array_unique($tags_post);
        }  

        # check if tags_news $tags_news exist
        if (count($tags_news) > 0) {

            # build data for new tag
            foreach ($tags_news as $name) {
                $post_tags[] = array(
                    'name' => strtolower($name),
                    'slug' => strtolower(slug($name)),
                );
            }

            # insert all new tag
            if ($this->_Process_MYSQL->insert_data_multiple($this->table_blog_post_tags, $post_tags, 'true')) {

                # read id after insert
                $read_tags = $this->_Process_MYSQL->get_data_multiple($this->table_blog_post_tags, $tags_news, 'name')->result();

                # create id for insert post
                foreach ($read_tags as $data_tags) {
                    $tags_id[] = $data_tags->id;
                }

                # check if add tag insert, join new tag and old tag
                if (!empty($tags_insert)) {
                    $all_tags = array_merge($tags_id, $tags_insert);
                } else {
                    $all_tags = $tags_id;
                }

                # set value post data tags
                $post_data['id_tags'] = implode(',', $all_tags);
                
            } else {
                #failed insert category
            }
            
            
        } else {

            # if no new tags
            foreach ($tags_post as $data_tags) {
                if (is_numeric($data_tags)) {
                    $tags_exist[] = $data_tags;
                }
            }


            $post_data['id_tags'] = implode(',', $tags_exist); 
        }
        
        return $post_data;        
    }

    public function data_update($id){
        return $this->_Process_MYSQL->get_data($this->table_blog_post,['id' => $id])->row_array();
    }

    public function process_create(){
        for ($i=0; $i < 100 ; $i++) { 
            $this->_Process_MYSQL->insert_data($this->table_blog_post,$this->data_post());
        }
        // return $this->_Process_MYSQL->insert_data($this->table_blog_post,$this->data_post());
    }

    public function process_update(){
        return $this->_Process_MYSQL->update_data($this->table_blog_post,$this->data_post(),['id' => $this->input->post('id')]);
    }   

    /**
     * Delete Blog Post
     * Delete Blog Post Comment
     */
    public function process_delete($id){
        if ($this->_Process_MYSQL->delete_data($this->table_blog_post, array('id' => $id)) == true AND $this->_Process_MYSQL->delete_data($this->table_blog_post_comment, array('id_blog_post' => $id)) == true) {
            return true;
        } else {
            return false;
        }
    }

    public function process_multiple_update($data){
        return $this->_Process_MYSQL->update_data_multiple($this->table_blog_post, $data, 'id');
    }

    /**
     * Delete Blog Post
     * Delete Blog Post Comment
     */
    public function process_multiple_delete($id){

        if ($this->_Process_MYSQL->delete_data_multiple($this->table_blog_post, $id, 'id') == true AND $this->_Process_MYSQL->delete_data_multiple($this->table_blog_post_comment, $id, 'id_blog_post') == true) {
            echo true;
        } else {
            echo false;
        }
    }    

}