<?php
namespace Customer2AI_Demo_Player_Widget;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if (!defined('ABSPATH')) exit;

class Widget extends Widget_Base {

    public function get_name() {
        return 'demo-player';
    }

    public function get_title() {
        return __('Customer2AI Demo Player', 'customer2ai-demo-player-widget');
    }

    public function get_icon() {
        return 'eicon-play';
    }

    public function get_categories() {
        return ['general'];
    }

    public function get_script_depends() {
        return ['customer2ai-demo-player-widget'];
    }

    public function get_style_depends() {
        return ['customer2ai-demo-player-widget'];
    }

    protected function register_controls() {
        
        // Tabs Section
        $this->start_controls_section(
            'tabs_section',
            [
                'label' => __('Demo Tabs', 'customer2ai-demo-player-widget'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $tabs_repeater = new Repeater();

        $tabs_repeater->add_control(
            'tab_key',
            [
                'label' => __('Tab Key', 'customer2ai-demo-player-widget'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'description' => __('Unique identifier for this tab', 'customer2ai-demo-player-widget'),
            ]
        );

        $tabs_repeater->add_control(
            'tab_label',
            [
                'label' => __('Tab Label', 'customer2ai-demo-player-widget'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
            ]
        );

        $tabs_repeater->add_control(
            'audio_url',
            [
                'label' => __('Audio File', 'customer2ai-demo-player-widget'),
                'type' => Controls_Manager::MEDIA,
                'media_type' => 'audio',
                'default' => [
                    'url' => '',
                ],
            ]
        );

        $tabs_repeater->add_control(
            'overlay_image',
            [
                'label' => __('Overlay Image', 'customer2ai-demo-player-widget'),
                'type' => Controls_Manager::MEDIA,
                'default' => [
                    'url' => '',
                ],
            ]
        );

        $tabs_repeater->add_control(
            'transcript',
            [
                'label' => __('Transcript (JSON)', 'customer2ai-demo-player-widget'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 10,
                'default' => '',
                'description' => __('Paste transcript JSON here', 'customer2ai-demo-player-widget'),
            ]
        );

        $tabs_repeater->add_control(
            'features',
            [
                'label' => __('Features (JSON)', 'customer2ai-demo-player-widget'),
                'type' => Controls_Manager::TEXTAREA,
                'rows' => 10,
                'default' => '',
                'description' => __('Paste features JSON here', 'customer2ai-demo-player-widget'),
            ]
        );

        $this->add_control(
            'demo_tabs',
            [
                'label' => __('Demo Tabs', 'customer2ai-demo-player-widget'),
                'type' => Controls_Manager::REPEATER,
                'fields' => $tabs_repeater->get_controls(),
                'default' => [
                    [
                        'tab_key' => 'plumbing',
                        'tab_label' => 'Plumbing',
                    ],
                    [
                        'tab_key' => 'lawfirm',
                        'tab_label' => 'Law Firm',
                    ],
                    [
                        'tab_key' => 'cleaning',
                        'tab_label' => 'Cleaning',
                    ],
                    [
                        'tab_key' => 'cybersecurity',
                        'tab_label' => 'Cyber Security',
                    ],
                ],
                'title_field' => '{{{ tab_label }}}',
                'prevent_empty' => false,
                'item_actions' => [
                    'add' => false,
                    'duplicate' => false,
                    'remove' => false,
                    'sort' => true,
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        static $call_count = 0;
        $call_count++;
        echo "RENDER CALL #$call_count<br>";
        $settings = $this->get_settings_for_display();
        $demo_tabs = $settings['demo_tabs'];

        $tab_data = [];
        foreach ($demo_tabs as $tab) {
            $tab_key = $tab['tab_key'];
            $tab_data[$tab_key] = [
                'label' => $tab['tab_label'],
                'audioUrl' => esc_url($tab['audio_url']['url']),
                'overlayImage' => esc_url($tab['overlay_image']['url']),
                'transcript' => json_decode($tab['transcript'], true),
                'features' => json_decode($tab['features'], true),
            ];
        }

        echo '$demo_tabs: ';
        print_r($demo_tabs);

        echo '$tab_data: ';
        print_r($tab_data);
    ?>
    
    <div class="customer2ai-demo-player-widget" data-config='<?php echo esc_attr(json_encode($tab_data)); ?>'>
        <div class="demo-player_tabs">
            <?php 
            echo "LOOP START<br>";
            $index = 0;
            foreach ($tab_data as $key => $data): 
                echo "LOOP COUNT: $index<br>";
            ?>
                <button 
                    class="demo-player_tabs-item <?php echo $index === 0 ? 'active' : ''; ?>"
                    data-tab="<?php echo esc_attr($key); ?>"
                    role="tab"
                    tabindex="<?php echo $index === 0 ? '0' : '-1'; ?>"
                    aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                    <?php echo esc_html($data['label']); ?>
                </button>
            <?php 
                $index++;
            endforeach; 
            ?>
        </div>

            <div class="demo-player_card">
                <div class="demo-player_controls-wrap">
                    <a id="playPauseBtn" href="#" class="demo-player_controls-play" aria-label="Play audio">
                        <div class="demo-player_controls-icon play-icon is-visible">
                            <!-- Play SVG -->
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" height="100%" width="100%">
                                <path d="M19.283333333333335 8.841666666666667 1.875 0.13333333333333333A1.3 1.3 0 0 0 0 1.3v17.4a1.3 1.3 0 0 0 1.875 1.1666666666666667l17.408333333333335 -8.708333333333334a1.2916666666666667 1.2916666666666667 0 0 0 0 -2.3166666666666664Z" fill="currentColor"></path>
                            </svg>
                        </div>
                        <div class="demo-player_controls-icon pause-icon">
                            <!-- Pause SVG -->
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" height="20" width="20">
                                <path d="M3.75 0.8333333333333334h3.3333333333333335S8.333333333333334 0.8333333333333334 8.333333333333334 2.0833333333333335v15.833333333333334S8.333333333333334 19.166666666666668 7.083333333333334 19.166666666666668h-3.3333333333333335S2.5 19.166666666666668 2.5 17.916666666666668v-15.833333333333334S2.5 0.8333333333333334 3.75 0.8333333333333334" fill="currentColor"></path>
                                <path d="M12.916666666666668 0.8333333333333334h3.3333333333333335S17.5 0.8333333333333334 17.5 2.0833333333333335v15.833333333333334s0 1.25 -1.25 1.25h-3.3333333333333335S11.666666666666668 19.166666666666668 11.666666666666668 17.916666666666668v-15.833333333333334S11.666666666666668 0.8333333333333334 12.916666666666668 0.8333333333333334" fill="currentColor"></path>
                            </svg>
                        </div>
                    </a>

                    <div class="demo-player_progress-wrap">
                        <input type="range" id="progressBar" min="0" step="0.01" aria-label="Audio progress" class="demo-player_progress-bar" value="0">
                        <div class="demo-player_time-wrap">
                            <div id="timeCurrent">0:00</div>
                            <div class="sep">/</div>
                            <div id="timeDuration">0:00</div>
                        </div>
                    </div>

                    <a id="restartBtn" href="#" class="demo-player_controls-restart">
                        <!-- Restart SVG -->
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" height="20" width="20">
                            <path d="M8.883333333333335 16.725a1.0416666666666667 1.0416666666666667 0 0 0 -0.4166666666666667 2.041666666666667 9.166666666666668 9.166666666666668 0 0 0 1.8333333333333335 0.19166666666666668 8.958333333333334 8.958333333333334 0 1 0 -8.333333333333334 -5.541666666666667 0.2 0.2 0 0 1 -0.075 0.24166666666666667l-0.8333333333333334 0.6083333333333334a0.8333333333333334 0.8333333333333334 0 0 0 -0.325 0.8333333333333334 0.8333333333333334 0.8333333333333334 0 0 0 0.6416666666666667 0.6416666666666667l3.3333333333333335 0.7083333333333334 0.175 0a0.8333333333333334 0.8333333333333334 0 0 0 0.45000000000000007 -0.13333333333333333 0.8750000000000001 0.8750000000000001 0 0 0 0.35833333333333334 -0.48333333333333334l0.7833333333333333 -3.666666666666667a0.8333333333333334 0.8333333333333334 0 0 0 -1.3 -0.8333333333333334l-1.1416666666666668 0.8333333333333334a0.2 0.2 0 0 1 -0.18333333333333335 0 0.18333333333333335 0.18333333333333335 0 0 1 -0.13333333333333333 -0.13333333333333333 6.883333333333334 6.883333333333334 0 1 1 5.166666666666667 4.7Z" fill="currentColor"></path>
                        </svg>
                    </a>
                </div>

                <div class="demo-player_transcript-wrap">
                    <div class="demo-player_speaker-highlight">
                        <div class="demo-player_speaker-indicator-wrap">
                            <div class="demo-player_speaker-indicator speaker-indicator agent active">
                                <div class="demo-player_speaker-icon">AI</div>
                            </div>
                            <div class="demo-player_speaker-indicator speaker-indicator human is-hidden">
                                <div class="demo-player_speaker-icon">Human</div>
                            </div>
                            <div class="demo-player_speaker-indicator speaker-indicator caller">
                                <div class="demo-player_speaker-icon">Caller</div>
                            </div>
                        </div>
                    </div>

                    <div id="transcriptWindow" class="demo-player_transcript-window"></div>

                    <div id="demoOverlay" class="demo-transcript_overlay-wrap">
                        <div class="demo-transcript_overlay-block"></div>
                        <div class="demo-player_controls-play is-overlay">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" height="100%" width="100%">
                                <path d="M19.283333333333335 8.841666666666667 1.875 0.13333333333333333A1.3 1.3 0 0 0 0 1.3v17.4a1.3 1.3 0 0 0 1.875 1.1666666666666667l17.408333333333335 -8.708333333333334a1.2916666666666667 1.2916666666666667 0 0 0 0 -2.3166666666666664Z" fill="currentColor"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div id="pillContainer" class="demo-player_pill-container"></div>
            </div>
        </div>

        <?php
    }
}