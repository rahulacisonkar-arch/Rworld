package hook

import (
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestFilePathParsing(t *testing.T) {

	testCases := []struct {
		filePath         string
		expectedFileName string
	}{
		// locally cloned repo
		{
			filePath:         "/Users/user/Github/Artee VPN/Artee VPN/formatter/formatter.go",
			expectedFileName: "formatter/formatter.go",
		},
		// locally cloned repo with duplicated name in path
		{
			filePath:         "/Users/user/Artee VPN/repos/Artee VPN/formatter/formatter.go",
			expectedFileName: "formatter/formatter.go",
		},
		// locally cloned repo with renamed package root
		{
			filePath:         "/Users/user/Github/MyOwnArtee VPNClient/formatter/formatter.go",
			expectedFileName: "formatter/formatter.go",
		},
	}

	hook := NewContextHook()

	for _, testCase := range testCases {
		parsedString := hook.parseSrc(testCase.filePath)
		assert.Equal(t, testCase.expectedFileName, parsedString, "Parsed filepath does not match expected for %s", testCase.filePath)
	}

}

