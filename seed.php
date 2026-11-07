<?php
/**
 * 개발 환경 시드 — blueprint.json 마지막 단계에서 실행된다 (npm run dev 마다 DB 가 새로 만들어지므로).
 *
 * 사이트 뼈대와 원고는 운영 서버와 같은 「우인 초기 설정」(wooin-core includes/setup.php)으로 넣고,
 * 여기서는 개발 전용만 더한다: 기본 샘플 글 삭제 · 로컬 유튜브 키 · [샘플] 소식 2건 · 영상 샘플(미리보기용).
 * 운영 서버에는 실행하지 않는다 (운영은 관리자 「도구 › 우인 초기 설정」).
 *
 * @package wooin-dev
 */

require '/wordpress/wp-load.php';

// 기본 샘플 글·페이지 제거 (개발 전용)
foreach ( get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
	wp_delete_post( $p->ID, true );
}

// 로컬 전용 YouTube API 키: dev/yt-key.txt (GitHub 에 올라가지 않음 — .gitignore). 없으면 RSS 로 동기화.
$key_file = __DIR__ . '/yt-key.txt';
if ( is_readable( $key_file ) ) {
	$yt            = get_option( 'wooin_youtube', array() );
	$yt['api_key'] = trim( (string) file_get_contents( $key_file ) );
	update_option( 'wooin_youtube', $yt );
}

// 운영과 같은 초기 설정. 브라우저 미리보기(WOOIN_PREVIEW)는 외부 요청이 막혀 유튜브 동기화를 건너뛴다.
wooin_core_setup(
	array(
		'hide_search' => true,
		'sync_videos' => ! defined( 'WOOIN_PREVIEW' ),
	)
);

// ---------------------------------------------------------------- [샘플] 우인 소식 2건 (공지 고정 · 구분 확인용 — 운영에는 넣지 않음)
$notice = wp_insert_post(
	array(
		'post_type'    => 'wooin_news',
		'post_status'  => 'publish',
		'post_title'   => '[샘플] 공지 글 예시 — 목록 맨 위에 고정됩니다',
		'post_content' => '<!-- wp:paragraph {"className":"wooin-placeholder"} --><p class="wooin-placeholder">실제 공지·소식 원고를 받으면 교체합니다. 관리자 화면 「게시판 설정 → 공지」를 체크하면 이 글처럼 목록 맨 위에 고정됩니다.</p><!-- /wp:paragraph -->',
	)
);
update_post_meta( $notice, 'pinned', true );
wp_set_object_terms( $notice, 'wooin', 'wooin_news_cat' );
$sample_news = wp_insert_post(
	array(
		'post_type'    => 'wooin_news',
		'post_status'  => 'publish',
		'post_title'   => '[샘플] 도산 소식 예시 — 법원 · 제도 변경 안내 자리',
		'post_content' => '<!-- wp:paragraph {"className":"wooin-placeholder"} --><p class="wooin-placeholder">회생법원 운영 변경, 제도 개정 등 도산 관련 소식을 올리는 자리입니다. 실제 원고로 교체합니다.</p><!-- /wp:paragraph -->',
		'post_date'    => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
	)
);
wp_set_object_terms( $sample_news, 'insolvency', 'wooin_news_cat' );

// 동기화가 안 됐으면(브라우저 미리보기·네트워크 장애) 두 채널의 실제 공개 영상 목록 샘플로 채운다 (dev/videos-sample.json, 2026-10-26 RSS 기준)
$sample_file = __DIR__ . '/videos-sample.json';
if ( function_exists( 'wooin_yt_upsert' ) && is_readable( $sample_file ) && ! get_posts( array( 'post_type' => 'wooin_video', 'posts_per_page' => 1, 'fields' => 'ids' ) ) ) {
	$yt_channels = array();
	foreach ( wooin_yt_settings()['channels'] as $i => $ch ) {
		$yt_channels[ $ch[0] ] = wooin_yt_playlist_term( $ch[0], $ch[1], -100 + $i );
	}
	foreach ( (array) json_decode( (string) file_get_contents( $sample_file ), true ) as $entry ) {
		wooin_yt_upsert( $entry + array( 'description' => '' ), (int) ( $yt_channels[ $entry['channel'] ] ?? 0 ) );
	}
}
wooin_setup_video_picks();
