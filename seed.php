<?php
/**
 * 개발 환경 시드 — blueprint.json 마지막 단계에서 실행된다 (npm run dev 마다 DB 가 새로 만들어지므로).
 *
 * 만드는 것: 서브 페이지 · 업무분야 4건 · 이서영 변호사 프로필 · 분야 분류 · 해결 사례(wooin.net 공개 성공사례 5건).
 * 운영 서버에는 실행하지 않는다. 운영 데이터는 관리자 화면에서 입력한다.
 *
 * @package wooin-dev
 */

require '/wordpress/wp-load.php';

// 기본 샘플 글·페이지 제거
foreach ( get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
	wp_delete_post( $p->ID, true );
}

$pattern = static fn( string $slug ): string => '<!-- wp:pattern {"slug":"wooin/' . $slug . '"} /-->';

/** 페이지 생성 (서브 페이지 템플릿). 반환: ID */
$page = static function ( string $slug, string $title, string $content, int $parent = 0, int $order = 0 ): int {
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_parent'  => $parent,
			'menu_order'   => $order,
			'post_content' => $content,
		)
	);
	update_post_meta( $id, '_wp_page_template', 'page-sub' );
	return (int) $id;
};

// ---------------------------------------------------------------- 페이지
$soon = $pattern( 'coming-soon' );
$page( 'about', '인사말', $pattern( 'page-greeting' ) );
$page( 'why', '왜 우인인가', $pattern( 'home-why' ) . $pattern( 'home-about' ) );
$page( 'news', '우인 소식', $soon );
$page( 'location', '오시는 길', $pattern( 'page-location' ) );
$page( 'procedure', '회생·파산 절차', $pattern( 'home-process' ) );
$page( 'support', '지원 사업 안내', $soon );
$page( 'major-cases', '주요 도산 사건', $soon );
$page( 'faq', '자주 묻는 질문', $pattern( 'page-faq' ) );
$page( 'notices', '회생·파산 기업 공고', $soon );
$page( 'consultation-guide', '상담 절차 안내', $pattern( 'page-guide' ) );
$page( 'privacy', '개인정보처리방침', $soon );
$page( 'disclaimer', '면책공고', $soon );
$page( 'email-policy', '이메일무단수집거부', $soon );

$form    = '<!-- wp:group {"className":"wooin-sub-section","layout":{"type":"constrained","contentSize":"860px"}} --><div class="wp-block-group wooin-sub-section">' . $pattern( 'consult-form' ) . '</div><!-- /wp:group -->';
$contact = $page( 'contact', '상담 신청', $form );
$page( 'personal', '개인회생·파산 상담 신청', $form, $contact );
$page( 'corporate', '법인회생·파산 상담 신청', $form, $contact );

// 칼럼 = 글 목록 페이지
// 칼럼 = 글 목록 페이지 (별도 글 목록을 쓰려면 첫 화면을 고정 페이지로 둬야 한다 — 첫 화면은 front-page.html 템플릿)
$front  = $page( 'home', '홈', '' );
$column = $page( 'column', '전문가 칼럼', '' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $front );
update_option( 'page_for_posts', $column );

// ---------------------------------------------------------------- 분야 분류
$areas = array();
foreach ( array(
	'personal-rehabilitation'  => '개인회생',
	'personal-bankruptcy'      => '개인파산',
	'corporate-rehabilitation' => '법인회생',
	'corporate-bankruptcy'     => '법인파산',
) as $slug => $name ) {
	$t              = wp_insert_term( $name, 'wooin_practice_area', array( 'slug' => $slug ) );
	$areas[ $slug ] = is_wp_error( $t ) ? (int) get_term_by( 'slug', $slug, 'wooin_practice_area' )->term_id : (int) $t['term_id'];
}

// ---------------------------------------------------------------- 업무분야 4건 (요약 = 상단 밴드 한 줄 소개)
$taglines = array(
	'personal-rehabilitation'  => '갚을 수 있는 만큼만 갚고, 다시 시작합니다.',
	'personal-bankruptcy'      => '더는 갚을 수 없을 때, 빚의 굴레를 끊습니다.',
	'corporate-rehabilitation' => '멈춘 회사를 다시 움직이게 합니다.',
	'corporate-bankruptcy'     => '책임 있게 정리하고, 대표님의 다음을 준비합니다.',
);
$order = 0;
foreach ( wooin_services_data() as $slug => $d ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'wooin_service',
			'post_status'  => 'publish',
			'post_title'   => $areas[ $slug ] ? get_term( $areas[ $slug ] )->name : $slug,
			'post_name'    => $slug,
			'post_excerpt' => $taglines[ $slug ] ?? '',
			'menu_order'   => ++$order,
			'post_content' => $pattern( 'service-' . $slug ),
		)
	);
	wp_set_object_terms( $id, $areas[ $slug ], 'wooin_practice_area' );
}

// ---------------------------------------------------------------- 구성원 (원고는 테마 inc/people.php — 상세 화면은 wooin/person-detail 블록)
$people = array(
	array( 'lee-seoyoung', '이서영 변호사', '9년간 오직 도산 분야만 연구한 법률사무소 우인 대표 변호사' ),
	array( 'seo-donggi', '서동기 회계사', '전 대전지방법원 회생법원 관리위원 · 법인회생 재무 자문' ),
	array( 'park-manyong', '박만용 세무사', '전 대전지방법원 조사위원 · 회생기업 세무 전담' ),
);
foreach ( $people as $i => $p ) {
	wp_insert_post(
		array(
			'post_type'    => 'wooin_person',
			'post_status'  => 'publish',
			'post_title'   => $p[1],
			'post_name'    => $p[0],
			'post_excerpt' => $p[2],
			'menu_order'   => $i,
		)
	);
}

// ---------------------------------------------------------------- 해결 사례 (wooin.net 공개 성공사례 — 표기 그대로)
$cases = array(
	array( '40대 생활비 채무 1억 5천만 원, 개인파산으로 전액 탕감', 'personal-bankruptcy', '40대 · 생활비', '1억 5천만 원', '전액 탕감', '100%', '', '면책결정' ),
	array( '50대 도박 채무 3억 7천만 원, 개인회생으로 2억 1천만 원 탕감', 'personal-rehabilitation', '50대 · 도박', '3억 7천만 원', '2억 1천만 원 탕감', '69%', '5년', '' ),
	array( '30대 생활비 채무 2천 8백만 원, 전액 탕감', 'personal-rehabilitation', '30대 · 생활비', '2천 8백만 원', '전액 탕감', '100%', '', '' ),
	array( '60대 사업 실패 채무 1억 8천만 원, 개인파산으로 전액 탕감', 'personal-bankruptcy', '60대 · 사업실패', '1억 8천만 원', '전액 탕감', '100%', '', '면책결정' ),
	array( '60대 생활비·병원비 채무 3천만 원, 개인파산으로 전액 탕감', 'personal-bankruptcy', '60대 · 생활비·병원비', '3천만 원', '전액 탕감', '100%', '', '면책결정' ),
);
$body = '<!-- wp:heading --><h2 class="wp-block-heading">사건 개요</h2><!-- /wp:heading -->'
	. '<!-- wp:paragraph {"className":"wooin-placeholder"} --><p class="wooin-placeholder">상황 · 쟁점 · 수행 내용 원고는 의뢰인 동의와 익명화 검수 후 게재됩니다. 현재는 wooin.net 에 공개된 결과 정보만 표시합니다.</p><!-- /wp:paragraph -->';
foreach ( $cases as $i => $c ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'wooin_case',
			'post_status'  => 'publish',
			'post_title'   => $c[0],
			'menu_order'   => $i + 1,
			'post_excerpt' => $c[2] . ' · 채무 ' . $c[3] . ' → ' . $c[4],
			'post_content' => $body,
		)
	);
	wp_set_object_terms( $id, $areas[ $c[1] ], 'wooin_practice_area' );
	foreach ( array( 'case_track' => 'personal', 'client_profile' => $c[2], 'debt_before' => $c[3], 'debt_result' => $c[4], 'reduction_rate' => $c[5], 'repay_period' => $c[6], 'result_stage' => $c[7] ) as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
}

// ---------------------------------------------------------------- 의뢰인 후기 (wooin.net 게시 네이버 카페 후기 — 본문·인용 원문 그대로)
// 원문 작성일을 알 수 없어 등록일은 시드 시각으로 두고, 카페 글 번호 순서(오래된 것 먼저)로 넣어 최신 글이 위에 오게 한다.
// 제목은 게시판 표시용으로 제작사가 붙였다(원문 제목 확인 시 교체).
$reviews = array(
	array( 17753, '키아드*', '회사가 무너진 뒤 남은 빚, 두 번의 보정 끝에 인가결정', '인가결정', true, '두번의 보정 진행 후 24년 10월 11일 인가결정을 받았습니다.', "갑작스럽게 망해버린 회사와 밀린 월급,퇴직금 ... 기존에 가지고 있던 빚으로 인해 삶이 한순간에 무너졌습니다.\n\n당장 쉬고있을 여유가 없어 급하게 이직한 직장에 급여로는 대출금 상환이 불가능해 퇴근후 배달, 대리운전 등으로 버텨 보았지만 상황은 나아지지 않았습니다.\n\n그러다 우연히 이서영변호사님 유튜브를 보게되었고 여기저기 알아 본 것도 없이 그냥 바로 연락을 하게되었습니다. 두번의 보정 진행 후 24년 10월 11일 인가결정을 받았습니다.\n\n3년 변제를 잘 마무리해서 이제는 빚없는 삶을 살고 싶습니다. 개인회생 같이 진행해주신 분들 너무나도 수고많으셨습니다." ),
	array( 17761, '오니*', '자영업 채무 1억, 여러 사무실을 비교한 끝에 우인을 선택했습니다', '', true, '제일 마음에 들고 신용이 갔던 우인을 선택하게 되었습니다.', "자영업을 하다 순식간에 생긴 1억원 넘는 채무로 개인회생을 알아보던 중 유투브를 통해 이서영변호사님을 보게 되었고 다른 변호사님들의 비해 귀에 쏙쏙들어오는 쉬운 설명과 뭔가의 아우라에 상담신청후 이승주 과장님과 상담을 하게되었습니다.\n\n사람을 쉽게 믿지 못해서 상담후 사무실 몇 곳을 한번 더 찾아가 눈으로 보고 한번 더 이야기를 나눈 후 제일 마음에 들고 신용이 갔던 우인을 선택하게 되었습니다.\n\n1년 동안 회생 준비하면서 모르는 것도 많았는데 친절하고 쉽게 설명해주셔서 감사했습니다. 저는 너무 만족했기에 다른분들께도 자신있게 추천드릴 수 있을 것 같습니다." ),
	array( 17769, '아라*', '10곳에서 거절당한 사건, 부부회생으로 85% 탕감', '개시결정', true, '85% 탕감률을 받았는데 정말 어려운 사건을 성공적으로 풀어내주셨습니다.', "여러 번의 거절을 당하고 좌절하던 중, 이서영 변호사님의 도움으로 어렵게 개인회생을 진행하게 된 후기를 작성합니다.\n\n처음 여러 변호사 사무실을 찾아갔을 때, 재산이 많다는 이유로 10곳 가까이 거절을 당햇습니다. 정말 막막했었죠.\n\n그러다 이서영변호사님만이 부부회생으로 재산을 나눠 신청하면 가능하다고 제안해주셔서 지푸라기라도 잡는 심정으로 6월 4일 개인회생을 신청했습니다. 예상보다 빠르게 진행되었고 이서영변호사님과 사무실에서 여러 방면으로 해결책을 찾아주어 8월 28일 개시결정이 나왔습니다. 85% 탕감률을 받았는데 정말 어려운 사건을 성공적으로 풀어내주셨습니다. 전문성과 끈기에 깊이 감사드립니다." ),
	array( 17793, '시우파**', '2차 보정 권고 없이, 한 달도 안 되어 개시결정', '개시결정', true, '1개월도 안되서 개시가나와서 정말 기쁨을 표현해도 부족하네요.', "지난 저의 불찰로인해 가정의위기를 느끼며 5년넘게 혼자 속앓이하며 지내다 우연히 유튜브에서 이서영변호사님 영상을 보게되어 와이프와 부모님께 전부 말씀드리고 개인회생 신청을 하게되었습니다.\n\n1차 보정권고로 제 잘못을 다시 한번 뉘우치고, 2차, 3차 보정이 나오면 어떻게 하나 정말 노심초사 걱정을 많이 했습니다.\n\n하지만 다행히 사무실에서 사건 처리를 완벽하게 도와줘서 저는 2차 보정 권고 없이 오늘 개시결정이 나왔습니다. 1개월도 안되서 개시가나와서 정말 기쁨을 표현해도 부족하네요. 다시한번 정말 감사드립니다." ),
	array( 17806, '김현*', '용기가 없어 버티기만 하다가, 개인회생 결정까지', '', false, '처음해보는 일에 아무것도 몰랐던 저를 친절하고 자세하게 설명해주셔서 개인회생 결정이 나게되어 정말 감사하게 생각하고 있습니다.', "몇년 전부터 친구의 권유에도 용기가 없어 다른 분들의 이야기만 들으며 한달한달을 버텨오고 있었는데 결국 감당할 수 없는 상황이 되어버렸고 그 동안 유튜브에서 보아오던 이서영 변호사님께 회생상담을 받아보자 생각하게 되었습니다.\n\n처음해보는 일에 아무것도 몰랐던 저를 친절하고 자세하게 설명해주셔서 개인회생 결정이 나게되어 정말 감사하게 생각하고 있습니다. 행복한 날들만 가득하시길 바랍니다. 감사합니다." ),
	array( 17810, '명*', '투자 실패로 남은 빚, 법원 관할 문제를 넘어 개시결정', '개시결정', false, '우연히 유튜브에서 이서영 변호사님의 영상을 보고 용기를 얻어 연락하고 사건을 진행하게 되었습니다.', "내집 마련을 목적으로 신용대출로 무리하게 지인 추천으로 투자했다가 빚만 남기고 막막했었습니다.\n\n우연히 유튜브에서 이서영 변호사님의 영상을 보고 용기를 얻어 연락하고 사건을 진행하게 되었습니다. 2024년 1월에 신청했고 지난달 10월에 개시결정이 났습니다.\n\n법원 관할 문제도 있젔지만 친절하게 진행해주셔서 문제없이 진행될 수 있었습니다. 착살히 변제금 납부해서 최종 면책결정까지 받아보도록 하겠습니다." ),
	array( 17811, '빚없는****', '정년퇴직 후 금융투자사기, 다시 시작한 회생', '', true, '그간 짙은 안개속같은 회생과정을 손잡아 이끌어 주신 사무실 담당직원분들께 감사드립니다.', "안녕하세요 드디어 고대하던 인가결정이 내려졌습니다.\n\n작년 정년퇴직을 하며 집장만을 계획했던 모든 일들이 금융투자사기를 당하며 물거품이 되었습니다. 거액의 채무로 예정되는 상황에 머리가 어지러웠는데 아들과 개인회생 이야기를 나누고 유튜브에서 봐뒀던 이서영변호사 사무실에 의뢰하기로 했습니다.\n\n2번의 보정만에 개시결정이 나왔고 6개월이 걸렸네요. 그간 짙은 안개속같은 회생과정을 손잡아 이끌어 주신 사무실 담당직원분들께 감사드립니다." ),
	array( 17862, '사육*', '화물 운송 개인사업자, 2억 원 넘는 채무로 개인회생', '', false, '지리적으로 찾아가기 편한 곳에 위치해 있어서 직접 상담을 받고 바로 결정했습니다.', "안녕하세요 저는 25톤 대형 화물차로 운송업을 하는 개인사업자입니다. 2017년 말 다니던 직장을 그만두고화물 운송업을 시작하게 되었습니다. 모든 것이 어리숙한 탓에 운송을 시작하기도 전 사고를 냈고, 몇 년을 힘들게 버텨봤지만 채무액은 줄지 않았습니다.\n\n상황을 반전시켜보려, 최대치로 대출을 받아 무리한 주식 투자를 시작했고 총 채무액은 2억을 넘어가게 되었습니다. 지리적으로 찾아가기 편한 곳에 위치해 있어서 직접 상담을 받고 바로 결정했습니다." ),
	array( 17865, '고마*', '프랜차이즈 폐업 후 생활이 막혔을 때, 개인회생 신청', '', false, '모르는 점 바로바로 친절히 알려주셔서 감사하구요 큰 도움 받아서 너무 감사드립니다.', "개인사업자로 프랜차이즈 운영하다가 매출은 점점 줄어드는데 인건비와 재료비는 점점 늘어나니 감당이 안되더라구요 ㅠㅠ 그래서 폐업 후 자녀 출산하고 나오는 부모급여로 나눠서 납부하다가 아예 생활이 불가능해져버려서 결국 이서영 변호사님 유튜브를 보고 상담 신청 후 개인회생을 신청하게 되었습니다....\n\n모르는 점 바로바로 친절히 알려주셔서 감사하구요 큰 도움 받아서 너무 감사드립니다." ),
	array( 17866, '븟븟', '전세사기 피해, 개인회생 인가와 아버지 채권자 인정까지', '인가결정', true, '작은 가능성이라도 놓지 않으시고 이야기를 들어주셔서 감사했습니다.', "저의 상황만 두고 봤을 때, 개인회생 인가가 나지 않을수도 있고, 아버지는 가족관계다 보니 채무가 아닌 증여로 볼 확률이 높아 채권자로 인정받기가 어렵다고 하셨지만 전세사기라는 사유를 인정받기 위해 집주인을 형사고소한 자료나, 아버지에게 이자명목으로 주기적으로 돈을 입금한 내역 등을 함께 증빙하며 어려운 상황에서도 진행해보겠다고 하셨습니다.\n\n그 결과 전세사기라는 사유로 개인회생도 인가 받았고 아버지도 채권자로 인정받았습니다.\n\n사실 그냥 안된다고 하시고 넘어가실 수도 있는데, 작은 가능성이라도 놓지 않으시고 이야기를 들어주셔서 감사했습니다." ),
);
foreach ( $reviews as $i => $r ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'wooin_review',
			'post_status'  => 'publish',
			// 등록 시각을 1분씩 띄워 「이전/다음 후기」가 순서대로 이어지게 한다
			'post_date'    => wp_date( 'Y-m-d H:i:s', time() - ( count( $reviews ) - $i ) * 60 ),
			'post_title'   => $r[2],
			'post_excerpt' => $r[5],
			'post_content' => implode( '', array_map( static fn( $p ) => '<!-- wp:paragraph --><p>' . esc_html( $p ) . '</p><!-- /wp:paragraph -->', explode( "\n\n", $r[6] ) ) ),
		)
	);
	wp_set_object_terms( $id, $areas['personal-rehabilitation'], 'wooin_practice_area' );
	update_post_meta( $id, 'reviewer', $r[1] );
	update_post_meta( $id, 'review_stage', $r[3] );
	update_post_meta( $id, 'source_url', 'https://cafe.naver.com/18997754/' . $r[0] );
	update_post_meta( $id, 'featured', $r[4] );
	update_post_meta( $id, 'consent_ok', true );
}

flush_rewrite_rules();

// 로컬 전용 YouTube API 키: dev/yt-key.txt (GitHub 에 올라가지 않음 — .gitignore). 없으면 RSS 로 동기화.
$key_file = __DIR__ . '/yt-key.txt';
if ( is_readable( $key_file ) ) {
	$yt            = get_option( 'wooin_youtube', array() );
	$yt['api_key'] = trim( (string) file_get_contents( $key_file ) );
	update_option( 'wooin_youtube', $yt );
}

// 마지막: 유튜브 동기화 (시간이 가장 오래 걸림). 브라우저 미리보기(WOOIN_PREVIEW)는 외부 요청이 막혀 건너뛴다.
if ( function_exists( 'wooin_yt_sync' ) && ! defined( 'WOOIN_PREVIEW' ) ) {
	wooin_yt_sync();
}
