<?php
use videoassess\va;

defined('MOODLE_INTERNAL') || die();

class mod_videoassessment_renderer extends plugin_renderer_base {
    /**
     *
     * @param renderable $widget
     * @return string
     */
    public function render(renderable $widget) {
        $rendermethod = 'render_'.str_replace('\\', '_', get_class($widget));
        if (method_exists($this, $rendermethod)) {
            return $this->$rendermethod($widget);
        }
        return $this->output->render($widget);
    }

    /**
     * @return string
     */
    public function header(va $va) {
        $this->page->set_title($va->va->name);

        $o = '';
        $o .= $this->output->header();
        $o .= $this->task_link($va);

        return $o;
    }

    /**
     * @return string
     */
    public function footer() {
        return $this->output->footer();
    }

    /**
     *
     * @return string
     */
    public function task_link(va $va) {
        $highlight = (object)array('upload' => null, 'associate' => null, 'assess' => null);
        $current = array('class' => 'tasklink-current');
        switch ($va->action) {
            case 'videos':
                $highlight->associate = $current;
                break;
            case 'assess':
                $highlight->assess = $current;
                break;
        }

        $o = '';
        if ($va->is_teacher()) {
            $links = array(
                    $this->output->action_link(new \moodle_url('/mod/videoassessment/bulkupload/index.php',
                            array('cmid' => $va->cm->id)),
                            get_string('uploadvideos', 'videoassessment'), $highlight->upload),
                    $this->output->action_link(new \moodle_url('/mod/videoassessment/view.php',
                            array('id' => $va->cm->id, 'action' => 'videos')),
                            get_string('associate', 'videoassessment'), null, $highlight->associate),
                    $this->output->action_link(new \moodle_url('/mod/videoassessment/view.php',
                            array('id' => $va->cm->id)),
                            get_string('assess', 'videoassessment'), null, $highlight->assess)
            );
            $o .= $this->output->box(implode(get_separator(), $links));
        }

        return $o;
    }

    /**
     *
     * @param videoassessment_video $video
     * @return string
     */
    public function render_videoassess_video(videoassess\video $video) {
        global $CFG;

        if ($CFG->release < 2012062500) {
            // Moodle 2.2
            require_once $CFG->dirroot.'/filter/mediaplugin/filter.php';
        }

        if (optional_param('novideo', 0, PARAM_BOOL)) {
            return;
        }
		if($video->data->tmpname == 'Youtube'){
			$url = $video->data->originalname;
		}else{
			$url = moodle_url::make_pluginfile_url(
					$video->context->id, 'mod_videoassessment', 'video', 0,
					$video->file->get_filepath(), $video->file->get_filename());
		}


        $url = (string)$url; // moodle_url->__toString()
        @$alt = $this->alt ?: $url;

        $width = !empty($video->width) ? $video->width : 400;
        $height = !empty($video->height) ? $video->height : 300;
        
        $dim = is_numeric($width) && is_numeric($height) && $width > 0 && $height > 0
        ? sprintf('#d=%dx%d', $width, $height)
        : '';
        
        $filter = new filter_mediaplugin($this->va->context, array());
        if (videoassess\va::check_mp4_support()) {
            // MP4形式をサポートするブラウザは HTML5 <video> タグ使用
            $prev_filter_mediaplugin_enable_html5video = !empty($CFG->filter_mediaplugin_enable_html5video);
            $CFG->filter_mediaplugin_enable_html5video = true;
            $html = $filter->filter('<a href="'.$url.$dim.'">'.$alt.'</a>');
            $CFG->filter_mediaplugin_enable_html5video = $prev_filter_mediaplugin_enable_html5video;
            return $html;
        }
        // それ以外のブラウザは FlowPlayer 使用
        // (Windows では QuickTime は一般的ではないので .mp4 にも FlowPlayer を使用する)

        // 拡張子が .mp4 だとFLVフィルタにマッチしないので、
        // ダミーの拡張子 .flv に付け替えてフィルタを通し、
        // 得られたHTMLを元の拡張子に書き換える
        $mp4 = null;
        if (preg_match('/\.mp4$/i', $url, $m)) {
            list ($mp4) = $m;
            $url = substr_replace($url, '.flv', -4);
        }
        $prev_filter_mediaplugin_enable_flv = !empty($CFG->filter_mediaplugin_enable_flv);
        $CFG->filter_mediaplugin_enable_flv = true;
        $html = $filter->filter('<a href="'.$url.$dim.'">'.$alt.'</a>');
        $CFG->filter_mediaplugin_enable_flv = $prev_filter_mediaplugin_enable_flv;
        if ($mp4) {
            $html = preg_replace('/\.flv(?=["#])/', $mp4, $html);
        }

        $o = $this->container($html, 'video');

        return $o;
    }
}
