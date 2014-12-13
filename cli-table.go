package main

import "fmt"

func table(a interface{}) {

}

func main() {
  list := map[string]string{}
  list["hello"] = "world"
  fmt.Println(list)
}