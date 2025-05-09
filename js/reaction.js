/** @format */

// 使用 jQuery 为点赞按钮添加点击事件
jQuery(document).ready(function ($) {
	// 全局存储已点赞的文章
	window.bslbLikedPosts = window.bslbLikedPosts || {};

	// 检查当前文章的点赞状态
	function checkLikeStatus() {
		// 获取页面上所有点赞按钮
		$('.bslb-like-button').each(function () {
			var $btn = $(this);
			var post_id = $btn.data('postid');

			// 如果没有post_id，跳过
			if (!post_id) return;

			// 如果已经在内存中有点赞状态，直接应用
			if (window.bslbLikedPosts[post_id]) {
				applyLikedState($btn, window.bslbLikedPosts[post_id]);
				return;
			}

			// 向服务器请求最新点赞状态
			$.post(
				bslb_vars.ajax_url,
				{
					action: 'bslb_check_like_status',
					post_id: post_id,
				},
				function (response) {
					if (response.success) {
						// 更新点赞计数
						$btn.find('.bslb-like-count').text(response.data.likes);

						// 保存点赞状态到内存中
						window.bslbLikedPosts[post_id] = {
							likes: response.data.likes,
							already_liked: response.data.already_liked,
						};

						// 如果用户已点赞，添加已点赞样式
						if (response.data.already_liked) {
							applyLikedState($btn, window.bslbLikedPosts[post_id]);
						}
					}
				},
			);
		});
	}

	// 应用点赞状态到按钮
	function applyLikedState($btn, state) {
		if (state && state.already_liked) {
			$btn.addClass('has-liked');
			$btn.find('.bslb-like-svg').addClass('has-liked');
			$btn.find('.bslb-like-count').addClass('has-liked');
			$btn.find('.bslb-like-count').text(state.likes);
		}
	}

	// 页面加载完成后检查点赞状态
	setTimeout(checkLikeStatus, 100);

	// 监听页面内容变化，用于检测上一篇/下一篇文章切换
	function setupContentChangeListeners() {
		// 方法1: 监听AJAX完成事件
		$(document).ajaxComplete(function (event, xhr, settings) {
			// 判断是否是加载新文章的AJAX请求
			setTimeout(function () {
				if ($('.bslb-like-button').length > 0) {
					checkLikeStatus();
					console.log('AJAX完成后发现点赞按钮，重新加载点赞状态');
				}
			}, 500);
		});

		// 方法2: 使用MutationObserver监听DOM变化
		if (typeof MutationObserver !== 'undefined') {
			const contentArea = document.querySelector('.site-content') || document.getElementById('content') || document.querySelector('article') || document.body;

			if (contentArea) {
				const observer = new MutationObserver(function (mutations) {
					// 检查变化中是否包含点赞按钮
					let hasLikeButton = false;
					mutations.forEach(function (mutation) {
						if (mutation.addedNodes && mutation.addedNodes.length) {
							for (let i = 0; i < mutation.addedNodes.length; i++) {
								const node = mutation.addedNodes[i];
								if (node.nodeType === 1) {
									// 元素节点
									if (node.querySelector && node.querySelector('.bslb-like-button')) {
										hasLikeButton = true;
										break;
									}
								}
							}
						}
					});

					if (hasLikeButton || $('.bslb-like-button').length > 0) {
						setTimeout(checkLikeStatus, 300);
						console.log('检测到DOM变化，发现点赞按钮，重新加载点赞状态');
					}
				});

				observer.observe(contentArea, {
					childList: true,
					subtree: true,
				});
			}
		}

		// 方法3: 监听页面导航事件
		window.addEventListener('popstate', function () {
			setTimeout(checkLikeStatus, 500);
			console.log('检测到页面导航，重新加载点赞状态');
		});

		// 方法4: 监听各种常见的上一篇/下一篇链接点击事件
		const navigationSelectors = [
			'a.next-post',
			'a.prev-post',
			'a.previous-post',
			'a.next',
			'a.prev',
			'a.pagination-next',
			'a.pagination-prev',
			'.nav-links a',
			'.pagination a',
			'.post-navigation a',
			'a.next-page',
			'a.prev-page',
			'.nav-previous a',
			'.nav-next a',
			'.pager a',
			'.page-numbers',
			'#nav-above a',
			'#nav-below a',
		];

		$(document).on('click', navigationSelectors.join(', '), function () {
			// 保存当前的点赞状态，以便在新页面加载时使用
			console.log('点击了导航链接，记录当前点赞状态');

			// 给新页面加载一点时间
			setTimeout(function () {
				checkLikeStatus();
				console.log('点击了导航链接，重新加载点赞状态');
			}, 1000);
		});

		// 方法5: 定期检查点赞按钮状态（作为备用措施）
		setInterval(function () {
			if ($('.bslb-like-button').length > 0) {
				checkLikeStatus();
			}
		}, 5000); // 每5秒检查一次
	}

	// 设置内容变化监听器
	setupContentChangeListeners();

	// 点赞按钮点击事件
	$(document).on('click', '.bslb-like-button', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $icon = $btn.find('.bslb-like-icon');
		var $svg = $btn.find('.bslb-like-svg');
		var $count = $btn.find('.bslb-like-count');
		var post_id = $btn.data('postid');

		// 防止重复点击
		if ($btn.hasClass('processing')) {
			return;
		}

		// 添加处理中状态
		$btn.addClass('processing').prop('disabled', true);

		$.post(
			bslb_vars.ajax_url,
			{
				action: 'bslb_like',
				post_id: post_id,
				nonce: bslb_vars.nonce,
			},
			function (response) {
				if (response.success) {
					// 更新点赞计数
					$count.text(response.data.likes);

					// 添加动画效果
					$icon.addClass('pulse');
					$svg.addClass('liked');

					// 如果是第一次点赞，添加持久的has-liked类
					if (response.data.already_liked && !$btn.hasClass('has-liked')) {
						$btn.addClass('has-liked');
						$svg.addClass('has-liked');
						$count.addClass('has-liked');
					}

					// 保存点赞状态到内存中
					window.bslbLikedPosts[post_id] = {
						likes: response.data.likes,
						already_liked: true,
					};

					// 动画结束后移除临时动画效果，但保留has-liked状态
					setTimeout(function () {
						$icon.removeClass('pulse');
						$svg.removeClass('liked');
					}, 500);

					// 输出调试信息到控制台
					console.log('点赞成功，当前点赞数: ' + response.data.likes);
				} else {
					// 显示错误消息
					console.log('点赞失败: ' + (response.data && response.data.message ? response.data.message : '未知错误'));
					alert(response.data && response.data.message ? response.data.message : '点赞失败，请稍后再试');
				}
				// 无论成功失败都移除处理中状态和禁用状态
				$btn.removeClass('processing').prop('disabled', false);
			},
		).fail(function (xhr, status, error) {
			console.log('AJAX错误: ' + status + ' - ' + error);
			alert('网络错误，请检查您的网络连接');
			// 恢复按钮状态
			$btn.removeClass('processing').prop('disabled', false);
		});
	});

	// 触摸设备支持
	$(document).on('touchend', '.bslb-like-button', function (e) {
		// 阻止移动设备上的多次触发
		e.preventDefault();
		// 模拟点击
		$(this).trigger('click');
	});
});
