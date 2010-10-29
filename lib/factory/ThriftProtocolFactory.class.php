<?php
/**
 * Description of ThriftProtocolFactory
 *
 * @author Tomasz Jakub Rup <tomasz.rup@gmail.com>
 */
final class ThriftProtocolFactory {
	/**
	 *
	 * @param string $configPostfix Config postfix
	 * @return TProtocol
	 */
	public static final function factory($configPostfix = 'default') {
		$config = sfConfig::get('app_thrift_plugin_'.$configPostfix);
		if(
			empty($config['transport'][0]) ||
			empty($config['transport'][1]) ||
			empty($config['protocol'])
		) {
			throw new Exception('Bad Thrift config');
		}

		$transport0 = self::getTransport0($config);
		$transport0->open();
		$transport1 = self::getTransport1($config, $transport0);

		return self::getProtocol($config, $transport1);
	}

	/**
	 *
	 * @param array $config
	 * @return TTransport
	 */
	private static function getTransport0($config) {
		switch($config['transport'][0]) {
			case 'THttpClient':
				if(!isset($config['host'])) throw new Exception ('Bad Thrift transport config');

				$host = $config['host'];
				$port = isset($config['port']) ? $config['port'] : 80;
				$uri = isset($config['uri']) ? $config['uri'] : '';
				$scheme = isset($config['scheme']) ? $config['scheme'] : 'http';
				$timeout = isset($config['timeout']) ? $config['timeout'] : null;

				$transport = new THttpClient($url, $port, $uri, $scheme);
				$transport->setTimeoutSecs($timeout);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create THttpClient with parameters: host = "%s", port = %d, uri = "%s", scheme = "%s", timeout = %d', $host, $port, $uri, $scheme, $timeout));
				break;
			case 'TMemoryBuffer':
				$buf = isset($config['buf']) ? $config['buf'] : '';

				$transport = new TMemoryBuffer($buf);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TMemoryBuffer with parameters: buf = "%s"', $buf));
				break;
			case 'TPhpStream':
				if(!isset($config['mode'])) throw new Exception ('Bad Thrift transport config');

				$mode = $config['mode'];

				$transport = new TPhpStream($mode);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TPhpStream with parameters: mode = %d', $mode));
				break;
			case 'TServerSocket':
				$host = isset($config['host']) ? $config['host'] : 'localhost';
				$port = isset($config['port']) ? $config['port'] : 9090;

				$transport = new TServerSocket($host, $port);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TServerSocket with parameters: host = "%s"', $host, $port));
				break;
			case 'TSocket':
				$host = isset($config['host']) ? $config['host'] : 'localhost';
				$port = isset($config['port']) ? $config['port'] : 9090;
				$persist = isset($config['persist']) ? $config['persist'] : false;
				$send_timeout = isset($config['send_timeout']) ? $config['send_timeout'] : 100;
				$recv_timeout = isset($config['recv_timeout']) ? $config['recv_timeout'] : 750;

				$transport = new TSocket($host, $port, $persist);
				$transport->setSendTimeout($send_timeout);
				$transport->setRecvTimeout($recv_timeout);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TSocket with parameters: host = "%s", port = %d, persist = %s, send_timeout = %d, recv_timeout = %d', $host, $port, $persist ? 'true' : 'false', $send_timeout, $recv_timeout));
				break;
			case 'TSocketPool':
				$hosts = isset($config['hosts']) ? $config['hosts'] : array('localhost');
				$ports = isset($config['ports']) ? $config['ports'] : array(9090);
				$persist = isset($config['persist']) ? $config['persist'] : false;
				$send_timeout = isset($config['send_timeout']) ? $config['send_timeout'] : 100;
				$recv_timeout = isset($config['recv_timeout']) ? $config['recv_timeout'] : 750;

				$transport = new TSocket($host, $port, $persist);
				$transport->setSendTimeout($send_timeout);
				$transport->setRecvTimeout($recv_timeout);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TSocketPool with parameters: hosts = ("%s"), ports = (%d), persist = %s, send_timeout = %d, recv_timeout = %d', implode('","', $hosts), implode('","', $ports), $persist ? 'true' : 'false', $send_timeout, $recv_timeout));
				break;
			default:
				throw new Exception('Unknown transport: '.$config['transport'][0]);
		}

		return $transport;
	}

	/**
	 *
	 * @param array $config
	 * @param TTransport $transport
	 * @return TTransport
	 */
	private static function getTransport1($config, TTransport $transport) {
		switch($config['transport'][1]) {
			case 'TBufferedTransport':
				$rBufSize = isset($config['read_buf_size']) ? $config['read_buf_size'] : 512;
				$wBufSize = isset($config['write_buf_size']) ? $config['write_buf_size'] : 512;

				$transport = new TBufferedTransport($transport, $rBufSize, $wBufSize);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TBufferedTransport with parameters: read_buf_size = %d, write_buf_size = %d', $rBufSize, $wBufSize));
				break;
			case 'TFramedTransport':
				$read = isset($config['read']) ? $config['read'] : true;
				$write = isset($config['write']) ? $config['write'] : true;

				$transport = new TFramedTransport($transport, $read, $write);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TFramedTransport with parameters: read = %s, write = %s', $read ? 'true' : 'false', $write ? 'true' : 'false'));
				break;
			case 'TNullTransport':
				$transport = new TNullTransport();

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TNullTransport'));
				break;
			default:
				throw new Exception('Unknown transport: '.$config['transport'][1]);
		}

		return $transport;
	}

	/**
	 *
	 * @param array $config
	 * @param TTransport
	 * @return TProtocol
	 */
	private static function getProtocol($config, TTransport $transport) {
		switch($config['protocol']) {
			case 'TBinaryProtocol':
				$strictRead = isset($config['strict_read']) ? $config['strict_read'] : false;
				$strictWrite = isset($config['strict_write']) ? $config['strict_write'] : true;

				$protocol = new TBinaryProtocol($transport, $strictRead, $strictWrite);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TBinaryProtocol with parameters: strictRead = %s, strictWrite = %s', $strictRead ? 'true' : 'false', $strictWrite ? 'true' : 'false'));
				break;
			case 'TBinaryProtocolAccelerated':
				$strictRead = isset($config['strict_read']) ? $config['strict_read'] : false;
				$strictWrite = isset($config['strict_write']) ? $config['strict_write'] : true;

				$protocol = new TBinaryProtocolAccelerated($transport, $strictRead, $strictWrite);

				sfContext::getInstance()->getLogger()->info(sprintf('{sfThriftPlugin}Create TBinaryProtocolAccelerated with parameters: strictRead = %s, strictWrite = %s', $strictRead ? 'true' : 'false', $strictWrite ? 'true' : 'false'));
				break;
		}

		return $protocol;
	}
}