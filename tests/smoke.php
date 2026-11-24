<?php
/** Isolated unit smoke checks for the logic and markup, not a WordPress integration test. */
define('ABSPATH', '/tmp/');
define('DAY_IN_SECONDS', 86400);
define('MINUTE_IN_SECONDS', 60);
define('AMINO_ORBIT_FILE', '/test/plugins/session-radar/session-radar.php');
define('WP_LANG_DIR', '/tmp/session-radar-smoke-wp-languages');
$state = [ 'admin'=>true, 'roles'=>['administrator'], 'options'=>['activity_enabled'=>1], 'meta'=>[], 'users'=>[] ];
class WP_User { public int $ID; public string $display_name; public array $roles; public function __construct($id,$name,$roles){$this->ID=$id;$this->display_name=$name;$this->roles=$roles;} }
class Test_Roles {public array $roles=['administrator'=>['name'=>'Administrator'],'editor'=>['name'=>'Editor'],'author'=>['name'=>'Author']];}
class WP_Session_Tokens {
    public static array $sessions=[];
    public static array $sessionsByUser=[];
    private int $id;
    public function __construct(int $id){$this->id=$id;}
    public static function get_instance($id){return new self((int)$id);}
    public function get_all(){return self::$sessionsByUser[$this->id] ?? self::$sessions;}
    public function get($token){return ($this->get_all())[0]??null;}
}
function wp_roles(){return new Test_Roles();}
function wp_parse_args($a,$b){return array_merge($b,$a);}
function get_option($key,$default=[]){return $GLOBALS['state']['options'];}
function current_user_can($cap){return $GLOBALS['state']['admin'];}
function is_user_logged_in(){return true;}
function wp_get_current_user(){return new WP_User(1,'Amino Studio',$GLOBALS['state']['roles']);}
function get_current_user_id(){return 1;}
function get_users($args){return $args['fields']==='ID'?array_keys($GLOBALS['state']['meta']):array_slice($GLOBALS['state']['users'],0,$args['number']??PHP_INT_MAX);}
function get_user_meta($id,$key,$single){return $GLOBALS['state']['meta'][$id]??[];}
function update_user_meta($id,$key,$value){$GLOBALS['state']['meta'][$id]=$value;}
function delete_user_meta($id,$key){unset($GLOBALS['state']['meta'][$id]);}
function wp_json_encode($a){return json_encode($a);}
function wp_salt($name){return 'smoke-secret';}
function absint($v){return abs((int)$v);}
function sanitize_key($s){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$s));}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return esc_html($s);}
function esc_attr__($s,$domain){return esc_attr($s);}
function esc_url($s){return esc_html($s);}
function esc_html_e($s,$d){echo esc_html($s);}
function __($s,$d){return $s;}
function wp_date($format,$timestamp){return gmdate($format,$timestamp);}
function human_time_diff($old,$new){return ((int)(($new-$old)/60)).' minutes';}
function admin_url($path){return 'https://example.test/wp-admin/'.$path;}
function translate_user_role($r){return $r;}
function checked($value,$match=null){if($value===$match)echo ' checked';}
function selected($value,$match=null){if($value===$match)echo ' selected';}
function settings_fields($a){}
function submit_button($a){}
function plugin_basename($f){return 'session-radar/session-radar.php';}
function determine_locale(){return 'en_US';}
function is_textdomain_loaded($domain){return false;}
function plugin_dir_path($file){return dirname($file).'/';}
function load_textdomain($domain,$path,$locale=null){$GLOBALS['state']['textdomain']=$path;return true;}
function assert_true($condition,$message){if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);} echo "PASS: $message\n";}
require dirname( __DIR__ ) . '/includes/class-amino-orbit.php';
Amino_Orbit::load_translations();
assert_true(!isset($state['textdomain']), 'English source locale does not attempt to load a catalog');
$state['users']=[new WP_User(1,'Amino Test',['administrator'])];
$session=['login'=>time()-120,'expiration'=>time()+3600,'ua'=>'Mozilla/5.0 Firefox/159 Windows NT 10.0','ip'=>'192.0.2.10'];
WP_Session_Tokens::$sessions=[$session];
$s=Amino_Orbit::sanitize_settings(['viewer_roles'=>['editor','administrator',['nested'], 'INVALID'], 'inactive_minutes'=>'9999','refresh_seconds'=>'7', 'show_ip'=>'1', 'activity_enabled'=>'1']);
assert_true($s['viewer_roles']===['editor'], 'Role configuration restricted to known non-admin roles');
assert_true($s['inactive_minutes']===5 && $s['refresh_seconds']===60, 'Numeric settings constrained to safe values');
$s30=Amino_Orbit::sanitize_settings(['refresh_seconds'=>30]);
assert_true($s30['refresh_seconds']===30, 'Accepts optional 30-second refresh interval');
function panel(){ob_start();Amino_Orbit::render_widget();return ob_get_clean();}
$html=panel();
assert_true(str_contains($html,'SESSION <em>RADAR</em>'), 'Renders Session Radar branding');
assert_true(str_contains($html,'Amino Test') && str_contains($html,'Session open'), 'Shows native session without falsely marking active');
assert_true(!str_contains($html,'192.0.2.10'), 'IP hidden by default');
assert_true(str_contains($html,'aria-label="Authenticated WordPress sessions"'),'Session list has accessible name');
$method=(new ReflectionClass('Amino_Orbit'))->getMethod('fingerprint');
// PHP 8.0 requires explicitly opening private methods; PHP 8.1+ ignores this call.
$method->setAccessible(true);
$key=$method->invoke(null,$session);
$state['meta'][1]=[$key=>time()];
assert_true(str_contains(panel(),'amino-orbit__status--active'), 'Marks recent admin interaction as active');
$state['meta'][1]=[$key=>time()-1000];
assert_true(str_contains(panel(),'amino-orbit__status--idle'), 'Marks stale interaction as inactive, without logging the user out');
$state['options']=['activity_enabled'=>1, 'show_ip'=>1];
assert_true(str_contains(panel(),'192.0.2.10'), 'Optional IP visible to administrator');
$state['admin']=false;
$state['roles']=['editor'];
assert_true(panel()==='', 'Non-admin is denied widget access by default');
$state['options']=['viewer_roles'=>['editor'],'activity_enabled'=>1, 'show_ip'=>1];
assert_true(str_contains(panel(),'Amino Test'), 'Configured viewer role can see the widget');
assert_true(!str_contains(panel(),'192.0.2.10'), 'Non-admin never sees IP even with IP setting enabled');
$state['meta'][2]=['expired'=>time()-90000];
Amino_Orbit::cleanup_presence();
assert_true(!isset($state['meta'][2]), 'Expired per-session data removed on scheduled cleanup');
assert_true(isset($state['meta'][1]), 'Recent per-session data preserved on cleanup');
// v0.2: visual semantics and multi-user simulation without writing site accounts.
$state['admin']=true;
$state['roles']=['administrator'];
$state['options']=['viewer_roles'=>[], 'activity_enabled'=>1, 'show_ip'=>0];
$state['users']=[
    new WP_User(1,'Amino Test',['administrator']),
    new WP_User(2,'Morgan <script>alert(1)</script>',['editor']),
];
$state['meta'] = [];
$now = time();
$s1=['login'=>$now-600,'expiration'=>$now+3600,'ua'=>'Mozilla/5.0 Firefox/159 Windows NT 10.0','ip'=>'192.0.2.10'];
$s2=['login'=>$now-1800,'expiration'=>$now+3600,'ua'=>'Mozilla/5.0 Safari/605 Mac OS X','ip'=>'192.0.2.11'];
$s3=['login'=>$now-3600,'expiration'=>$now+3600,'ua'=>'Mozilla/5.0 Chrome/130 Android','ip'=>'192.0.2.12'];
$expired=['login'=>$now-87000,'expiration'=>$now-1800,'ua'=>'test','ip'=>'192.0.2.13'];
WP_Session_Tokens::$sessionsByUser=[1=>[$s1,$s2,$expired], 2=>[$s3]];
$key1=$method->invoke(null,$s1);
$key2=$method->invoke(null,$s2);
$key3=$method->invoke(null,$s3);
$state['meta'][1]=[$key1=>$now-10,$key2=>$now-900];
$state['meta'][2]=[$key3=>$now-120];
$html=panel();
assert_true(substr_count($html,'data-orbit-status="')===7, 'Accessible filter toolbar and session status data are present');
assert_true(str_contains($html, 'aria-pressed="true"') && str_contains($html, 'aria-live="polite"'), 'Filter state and visible-count announcement are accessible');
assert_true(str_contains($html, 'data-orbit-status="idle"') && str_contains($html, 'data-orbit-status="active"'), 'Filter metadata uses established activity categories');
assert_true(str_contains($html, 'class="amino-orbit__reset"') && str_contains($html, 'class="amino-orbit__compact-toggle"') && str_contains($html, 'class="amino-orbit__empty-filter"'), 'Reset, compact toggle and no-matches status are present');
assert_true(str_contains($html, 'class="amino-orbit__role-filter"') && str_contains($html, 'data-orbit-roles='), 'Authorized role filter and row role metadata are rendered');
assert_true(str_contains($html,'class="amino-orbit__refresh-now"') && str_contains($html,'role="status"'), 'Accessible manual refresh button and feedback region exist');
assert_true(str_contains($html, 'class="amino-orbit__sort"') && str_contains($html, 'data-orbit-login=') && str_contains($html, 'data-orbit-order='), 'Local sort field and stable order keys are rendered');
assert_true(str_contains($html,'class="amino-orbit__search"') && str_contains($html,'type="search"') && str_contains($html,'autocomplete="off"'), 'Search input is labeled and disables browser autofill');
assert_true(substr_count($html,'class="amino-orbit__user"')===3,'Three valid sessions displayed, expired session ignored');
assert_true(str_contains($html,'Morgan &lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html,'<script>alert(1)</script>'),'Display names are HTML-escaped');
assert_true(str_contains($html,'Firefox · Windows') && str_contains($html,'Chrome · Android'),'Device labels derived from user agents');
assert_true(str_contains($html,'Logged in: ') && str_contains($html,'title="Automatic refresh'),'Login times clarified and auto-refresh identified without live claim');
assert_true(substr_count($html,'amino-orbit__status--active')===2 && substr_count($html,'amino-orbit__status--idle')===1,'Mixed sessions correctly classified by recent activity');
$state['admin']=false;
$state['roles']=['author'];
$state['options']=['viewer_roles'=>['editor'], 'activity_enabled'=>1, 'show_ip'=>1];
assert_true(panel()==='', 'Unapproved author role denied despite allowed editor role');
$state['roles']=['editor'];
assert_true(!str_contains(panel(),'192.0.2.'),'Authorized editor cannot see native IP addresses');
WP_Session_Tokens::$sessionsByUser=[];
$state['admin']=true;
$state['roles']=['administrator'];
$state['options']=['activity_enabled'=>1];
$state['users']=[];
for($id=1;$id<=201;$id++) $state['users'][]=new WP_User($id,'Test User '.$id,['subscriber']);
$html=panel();
assert_true(str_contains($html, 'Filters and sorting apply only to displayed sessions.'), 'Truncated lists disclose sorting and filtering scope');
assert_true(str_contains($html,'Partial results: the session scan limit has been reached.'),'201 accounts disclose truncated scan');
assert_true(str_contains($html,'>200</strong><small>Sessions'),'Account scan is capped at 200');
$state['users']=[new WP_User(1,'Test Admin',['administrator'])];
$lots=[];
for($i=1;$i<=510;$i++) $lots[]=['login'=>time()-$i,'expiration'=>time()+3600,'ua'=>'Firefox/159 Windows','ip'=>'192.0.2.10'];
WP_Session_Tokens::$sessionsByUser=[1=>$lots];
$html=panel();
assert_true(str_contains($html,'Partial results: the session scan limit has been reached.'),'510 sessions disclose truncated scan');
assert_true(str_contains($html,'>500</strong><small>Sessions'),'Session scan capped at 500');
assert_true(substr_count($html,'class="amino-orbit__user"')===40,'40-session UI row cap remains');
WP_Session_Tokens::$sessionsByUser=[];
echo "All smoke checks passed.\n";
